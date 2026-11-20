<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Models\OrganizationEntity;

/** Gemeinsame Helfer der management-book.books.*-Tools (Team-Auflösung, Serialisierung, Knoten-Schreibrecht). */
trait HandlesManagementBooks
{
    /** @return array{team: Team|null, error: ToolResult|null} */
    protected function resolveTeam(array $arguments, ToolContext $context): array
    {
        if (! $context->user) {
            return ['team' => null, 'error' => ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }

        $teamId = $arguments['team_id'] ?? null;
        if ($teamId === 0 || $teamId === '0') {
            $teamId = null;
        }
        $teamId = $teamId ?: $context->team?->id;
        if (! $teamId) {
            return ['team' => null, 'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext. Nutze "core.teams.GET".')];
        }

        $team = $context->user->teams()->where('teams.id', (int) $teamId)->first();
        if (! $team) {
            return ['team' => null, 'error' => ToolResult::error('ACCESS_DENIED', "Kein Zugriff auf Team {$teamId}. Nutze 'core.teams.GET'.")];
        }

        return ['team' => $team, 'error' => null];
    }

    /**
     * Darf der User an diesen Knoten verknüpfen? Gleiche Regel wie die Terminal-App "Verknüpfungen":
     * Entity muss im Schreib-Reichweitenbereich des Users liegen (Core-Graph, kein eigenes Rollensystem).
     */
    protected function canWriteEntity(ToolContext $context, int $entityId): bool
    {
        if (! class_exists(\Platform\Organization\Authz\DimensionLinkAuthorizer::class)) {
            return false;
        }

        return in_array($entityId, (new \Platform\Organization\Authz\DimensionLinkAuthorizer())->writableEntityIds($context->user), true)
            && OrganizationEntity::whereKey($entityId)->exists();
    }

    protected function authorizeBook(ToolContext $context, string $ability, ManagementBook $book): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $book)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für Management Book #{$book->id}.");
    }

    /** @return array<string,mixed> */
    protected function bookToArray(ManagementBook $book): array
    {
        $nodeIds = app(ManagementBookService::class)->nodeIds($book);
        $names = $nodeIds === [] ? collect() : OrganizationEntity::whereIn('id', $nodeIds)->pluck('name', 'id');

        return [
            'id' => $book->id,
            'uuid' => $book->uuid,
            'team_id' => $book->team_id,
            'title' => $book->title,
            'description' => $book->description,
            'status' => $book->status?->value,
            'status_label' => $book->status?->label(),
            'activated_at' => $book->activated_at?->toIso8601String(),
            'valid_from' => $book->valid_from?->toDateString(),
            'valid_until' => $book->valid_until?->toDateString(),
            'horizon_year' => $book->horizon_year,
            'owner_user_id' => $book->owner_user_id,
            'responsible_agent_user_id' => $book->responsible_agent_user_id,
            'review_rhythm' => $book->review_rhythm?->value,
            'next_review_at' => $book->next_review_at?->toDateString(),
            'created_by_user_id' => $book->created_by_user_id,
            'nodes' => array_map(fn ($id) => ['entity_id' => $id, 'entity_name' => $names[$id] ?? null], $nodeIds),
            'created_at' => $book->created_at?->toIso8601String(),
            'updated_at' => $book->updated_at?->toIso8601String(),
        ];
    }

    /** Gemeinsame Property-Definitionen für POST/PUT. */
    protected function bookFieldSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'description' => 'Titel des Management Books.'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung / Zweck des Books.'],
            'valid_from' => ['type' => 'string', 'description' => 'Gültig ab (YYYY-MM-DD). Rein informativ, Überschneidungen mit anderen Books sind erlaubt.'],
            'valid_until' => ['type' => 'string', 'description' => 'Gültig bis (YYYY-MM-DD). Rein informativ.'],
            'horizon_year' => ['type' => 'integer', 'description' => 'Zieljahr/Horizont, z. B. 2032.'],
            'owner_user_id' => ['type' => 'integer', 'description' => 'Owner (User-ID, Mitglied des Teams). Default beim Anlegen: aufrufender User.'],
            'responsible_agent_user_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users, type ai_user).'],
            'review_rhythm' => ['type' => 'string', 'enum' => \Platform\ManagementBook\Enums\ReviewRhythm::values(), 'description' => 'Review-Rhythmus.'],
            'next_review_at' => ['type' => 'string', 'description' => 'Nächstes Review (YYYY-MM-DD).'],
        ];
    }
}
