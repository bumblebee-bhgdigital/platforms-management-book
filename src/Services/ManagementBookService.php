<?php

namespace Platform\ManagementBook\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\ReviewRhythm;
use Platform\ManagementBook\Events\ManagementBookLinkChanged;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Use-Cases rund ums Management Book (analog ProjectCreationService/LifecycleService im Planner).
 *
 * Der Service autorisiert NICHT — Rechte werden an der Grenze (Livewire, MCP-Tool) per
 * Gate/ManagementBookPolicy geprüft, wie im Planner. Er validiert Fachregeln und hält die Invariante:
 *
 *   Pro Knoten (Organisations-Entity) gibt es höchstens EIN Book mit Status "active".
 *
 * Aktivieren (oder Verknüpfen/Umhängen eines aktiven Books) löst das bisher aktive Book desselben
 * Knotens automatisch ab (-> superseded, bleibt als Historie erhalten). Ein Book ohne Knoten
 * ("ungeknotet") unterliegt der Regel nicht: ungeknotete Books können beliebig viele aktiv sein.
 * Der Knoten wird erst bei der Verknüpfung relevant — dann greift die Regel (siehe enforceSingleActive).
 */
class ManagementBookService
{
    /** Felder, die create()/update() aus $data übernehmen. */
    private const FIELDS = [
        'title', 'description', 'valid_from', 'valid_until', 'horizon_year',
        'owner_user_id', 'responsible_agent_user_id', 'review_rhythm', 'next_review_at',
    ];

    // ── Lesen ────────────────────────────────────────────────────

    public function find(int $id, bool $withTrashed = false): ?ManagementBook
    {
        $query = ManagementBook::query();
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Books eines Teams (optional Status-Filter). Sichtbarkeit ($viewer) = Graph-Scope wie die UI.
     *
     * @param int|int[] $teamIds
     */
    public function listForTeams(int|array $teamIds, ?User $viewer = null, ?ManagementBookStatus $status = null): Collection
    {
        $query = ManagementBook::query()
            ->whereIn('team_id', (array) $teamIds)
            ->orderBy('title');

        if ($viewer) {
            $query->visibleTo($viewer);
        }
        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /** "Aktives Book eines Knotens" — null, wenn am Knoten keins aktiv ist. */
    public function findActiveForNode(int $entityId): ?ManagementBook
    {
        $ids = $this->bookIdsAtNode($entityId);
        if ($ids === []) {
            return null;
        }

        return ManagementBook::query()->whereIn('id', $ids)->status(ManagementBookStatus::ACTIVE)->first();
    }

    /** Alle Books eines Knotens (jeder Status, ohne soft-gelöschte). */
    public function booksForNode(int $entityId): Collection
    {
        $ids = $this->bookIdsAtNode($entityId);

        return $ids === [] ? new Collection() : ManagementBook::query()->whereIn('id', $ids)->orderBy('title')->get();
    }

    /** Knoten (Entity-IDs), an denen das Book hängt. @return int[] */
    public function nodeIds(ManagementBook $book): array
    {
        return EntityDimensionBridge::linksForLinkables([ManagementBook::MORPH_ALIAS], [$book->id], false)
            ->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ── Anlegen / Ändern ─────────────────────────────────────────

    /**
     * @param array<string,mixed> $data title (Pflicht) + Felder aus self::FIELDS, optional status (draft|active)
     * @param int|null $entityId optionaler Knoten, an den das Book gleich gehängt wird
     */
    public function create(User $creator, Team|int $team, array $data, ?int $entityId = null): ManagementBook
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ManagementBookException('Titel ist erforderlich.');
        }

        $status = ManagementBookStatus::DRAFT;
        if (! empty($data['status'])) {
            $status = ManagementBookStatus::tryFrom((string) $data['status'])
                ?? throw new ManagementBookException('status muss einer von ' . implode(', ', ManagementBookStatus::values()) . ' sein.');
            if (! in_array($status, [ManagementBookStatus::DRAFT, ManagementBookStatus::ACTIVE], true)) {
                throw new ManagementBookException('Neue Books starten als draft oder active.');
            }
        }

        $attrs = $this->validated($data, $teamId, null);
        $attrs['title'] = $title;
        $attrs['team_id'] = $teamId;
        $attrs['created_by_user_id'] = $creator->id;
        $attrs['owner_user_id'] = $attrs['owner_user_id'] ?? $creator->id;
        $attrs['status'] = ManagementBookStatus::DRAFT;

        return DB::transaction(function () use ($attrs, $entityId, $status) {
            $book = ManagementBook::create($attrs);

            if ($entityId) {
                $this->link($book, $entityId);
            }
            if ($status === ManagementBookStatus::ACTIVE) {
                $this->activate($book);
            }

            return $book->refresh();
        });
    }

    /** @param array<string,mixed> $data nur gesetzte Schlüssel werden geändert (null leert, wo erlaubt) */
    public function update(ManagementBook $book, array $data): ManagementBook
    {
        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            throw new ManagementBookException('Titel darf nicht leer sein.');
        }

        $attrs = $this->validated($data, $book->team_id, $book);
        if (array_key_exists('title', $data)) {
            $attrs['title'] = trim((string) $data['title']);
        }

        $book->fill($attrs)->save();

        return $book->refresh();
    }

    // ── Statuswechsel ────────────────────────────────────────────

    /**
     * Aktiviert das Book. Löst pro Knoten des Books das bisher aktive Book ab.
     *
     * @return array{book: ManagementBook, superseded: ManagementBook[]}
     */
    public function activate(ManagementBook $book): array
    {
        if ($book->trashed()) {
            throw new ManagementBookException('Ein gelöschtes Book kann nicht aktiviert werden.', 'INVALID_STATE');
        }
        if (! $book->status->canActivate()) {
            throw new ManagementBookException("Ein Book im Status '{$book->status->value}' kann nicht aktiviert werden (nur draft/superseded).", 'INVALID_STATE');
        }

        return DB::transaction(function () use ($book) {
            if ($book->status !== ManagementBookStatus::ACTIVE) {
                $book->status = ManagementBookStatus::ACTIVE;
                $book->activated_at = now();
                $book->save();
            }

            return ['book' => $book->refresh(), 'superseded' => $this->enforceSingleActive($book)];
        });
    }

    public function archive(ManagementBook $book): ManagementBook
    {
        if ($book->status !== ManagementBookStatus::ARCHIVED) {
            $book->status = ManagementBookStatus::ARCHIVED;
            $book->save();
        }

        return $book->refresh();
    }

    /** Soft-Delete (wiederherstellbar). Die Knoten-Verknüpfung bleibt bestehen, wie beim Planner-Projekt. */
    public function delete(ManagementBook $book): void
    {
        $book->delete();
    }

    /**
     * Invariante herstellen: ist $book aktiv, werden alle ANDEREN aktiven Books an den Knoten von $book abgelöst.
     * Idempotent; auch von Link-/Umhänge-Pfaden und vom DimensionLink-Hook aufgerufen.
     *
     * @return ManagementBook[] die abgelösten Books
     */
    public function enforceSingleActive(ManagementBook $book): array
    {
        if ($book->status !== ManagementBookStatus::ACTIVE) {
            return [];
        }

        $superseded = [];
        foreach ($this->nodeIds($book) as $entityId) {
            $otherIds = array_diff($this->bookIdsAtNode($entityId), [$book->id]);
            if ($otherIds === []) {
                continue;
            }
            $others = ManagementBook::query()
                ->whereIn('id', $otherIds)
                ->status(ManagementBookStatus::ACTIVE)
                ->lockForUpdate()
                ->get();

            foreach ($others as $other) {
                $other->status = ManagementBookStatus::SUPERSEDED;
                $other->save();
                $superseded[$other->id] = $other;
                ManagementBookLinkChanged::dispatch($other, ManagementBookLinkChanged::SUPERSEDED, $entityId);
            }
        }

        return array_values($superseded);
    }

    // ── Knoten-Verknüpfung (Dimension Bridge) ────────────────────

    /** Book an einen Knoten hängen (zusätzlich zu bestehenden Knoten). Ist das Book aktiv, löst es das dortige aktive Book ab. */
    public function link(ManagementBook $book, int $entityId): void
    {
        EntityDimensionBridge::createLink($entityId, ManagementBook::MORPH_ALIAS, $book->id, ['team_id' => $book->team_id]);
        EntityDimensionBridge::flush();

        // Event LINKED + Regel-Durchsetzung kommen zusätzlich aus dem DimensionLink-Hook (ServiceProvider);
        // enforceSingleActive ist idempotent und sichert den Fall eines bereits bestehenden Links.
        $this->enforceSingleActive($book);
    }

    /** Verknüpfung zu einem Knoten lösen; ohne $entityId alle Knoten (Book wird "ungeknotet"). */
    public function unlink(ManagementBook $book, ?int $entityId = null): void
    {
        $nodes = $entityId ? [$entityId] : $this->nodeIds($book);
        foreach ($nodes as $id) {
            EntityDimensionBridge::deleteLink($id, ManagementBook::MORPH_ALIAS, $book->id);
            ManagementBookLinkChanged::dispatch($book, ManagementBookLinkChanged::UNLINKED, $id);
        }
        EntityDimensionBridge::flush();
    }

    /** Umhängen: ersetzt alle bisherigen Knoten durch $newEntityId (wie ProjectsCleanup im Planner). */
    public function relink(ManagementBook $book, int $newEntityId): void
    {
        $previous = $this->nodeIds($book)[0] ?? null;

        EntityDimensionBridge::replaceLinks(ManagementBook::MORPH_ALIAS, $book->id, $newEntityId, ['team_id' => $book->team_id]);
        EntityDimensionBridge::flush();

        $this->enforceSingleActive($book);
        ManagementBookLinkChanged::dispatch($book, ManagementBookLinkChanged::RELINKED, $newEntityId, $previous);
    }

    // ── intern ───────────────────────────────────────────────────

    /** @return int[] Book-IDs, die am Knoten hängen */
    private function bookIdsAtNode(int $entityId): array
    {
        return EntityDimensionBridge::linksForEntities([$entityId])
            ->where('linkable_type', ManagementBook::MORPH_ALIAS)
            ->pluck('linkable_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Validiert/normalisiert die Eingabefelder. Nur vorhandene Schlüssel kommen zurück.
     *
     * @return array<string,mixed>
     */
    private function validated(array $data, int $teamId, ?ManagementBook $existing): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data) && $field !== 'title') {
                $out[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (isset($out['review_rhythm'])) {
            ReviewRhythm::tryFrom((string) $out['review_rhythm'])
                ?? throw new ManagementBookException('review_rhythm muss einer von ' . implode(', ', ReviewRhythm::values()) . ' sein.');
        }

        foreach (['valid_from', 'valid_until', 'next_review_at'] as $dateField) {
            if (! empty($out[$dateField])) {
                try {
                    $out[$dateField] = Carbon::parse($out[$dateField])->toDateString();
                } catch (\Throwable) {
                    throw new ManagementBookException("{$dateField} ist kein gültiges Datum (YYYY-MM-DD).");
                }
            }
        }

        $from = array_key_exists('valid_from', $out) ? $out['valid_from'] : $existing?->valid_from?->toDateString();
        $until = array_key_exists('valid_until', $out) ? $out['valid_until'] : $existing?->valid_until?->toDateString();
        if ($from && $until && $until < $from) {
            throw new ManagementBookException('valid_until darf nicht vor valid_from liegen. (Überschneidung mit anderen Books ist dagegen erlaubt.)');
        }

        if (! empty($out['horizon_year'])) {
            $year = (int) $out['horizon_year'];
            if ($year < 1900 || $year > 2200) {
                throw new ManagementBookException('horizon_year muss zwischen 1900 und 2200 liegen.');
            }
            $out['horizon_year'] = $year;
        }

        if (! empty($out['owner_user_id'])) {
            $owner = User::find((int) $out['owner_user_id']);
            if (! $owner || ! $owner->teams()->where('teams.id', $teamId)->exists()) {
                throw new ManagementBookException('owner_user_id ist kein Mitglied des Teams.');
            }
        }

        if (! empty($out['responsible_agent_user_id'])) {
            $agent = User::find((int) $out['responsible_agent_user_id']);
            if (! $agent || $agent->type !== 'ai_user') {
                throw new ManagementBookException('responsible_agent_user_id muss ein Agent-User (type ai_user) sein.');
            }
        }

        return $out;
    }
}
