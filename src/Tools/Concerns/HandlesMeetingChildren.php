<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\Team;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookDocument;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Models\ManagementBookMeetingAttendee;
use Platform\ManagementBook\Models\ManagementBookMinutes;

/**
 * CRUD-Logik für die Kinder einer Sitzung (#1052): agenda_item, document, minutes, decision, attendee.
 * Jede Tool-Klasse liefert nur Name/Beschreibung und ruft run*() mit ihrer Art auf.
 *
 * Reine Datenhaltung: alle Felder sind frei schreibbar (auch approved_at, attended, status), damit historische
 * Sitzungen vollständig abbildbar sind. Es gibt keine Standard-Agenda und keine Ablaufregeln.
 * Rechte: Sitzungs-Kinder erben die Rechte der Sitzung (ManagementBookMeetingChildPolicy).
 */
trait HandlesMeetingChildren
{
    use HandlesStrategy;

    /** @return array{label:string, plural:string, class:class-string<Model>, fields:string[], created_by:bool, agenda:bool, search:string[]} */
    protected function childConfig(string $kind): array
    {
        return match ($kind) {
            'agenda_item' => [
                'label' => 'Agenda-Punkt', 'plural' => 'agenda_items', 'class' => ManagementBookAgendaItem::class,
                'fields' => ['position', 'title', 'description', 'owner_user_id', 'owner_agent_id', 'duration_minutes', 'linked_object_type', 'linked_object_id',
                    'goal', 'observation', 'obstacle', 'local_solution', 'system_support', 'organization_entity_id', 'outcome_checks'],
                'created_by' => true, 'agenda' => false, 'search' => ['title', 'description'],
            ],
            'document' => [
                'label' => 'Unterlage', 'plural' => 'documents', 'class' => ManagementBookDocument::class,
                'fields' => ['agenda_item_id', 'title', 'type'],
                'created_by' => true, 'agenda' => true, 'search' => ['title', 'type'],
            ],
            'minutes' => [
                'label' => 'Protokoll', 'plural' => 'minutes', 'class' => ManagementBookMinutes::class,
                'fields' => ['content', 'approved_at', 'approved_by'],
                'created_by' => true, 'agenda' => false, 'search' => ['content'],
            ],
            'decision' => [
                'label' => 'Beschluss', 'plural' => 'decisions', 'class' => ManagementBookDecision::class,
                'fields' => ['agenda_item_id', 'text', 'status', 'owner_user_id', 'owner_agent_id', 'due_date', 'linked_task_id'],
                'created_by' => true, 'agenda' => true, 'search' => ['text'],
            ],
            'attendee' => [
                'label' => 'Teilnehmer', 'plural' => 'meeting_attendees', 'class' => ManagementBookMeetingAttendee::class,
                'fields' => ['user_id', 'agent_id', 'role', 'attended'],
                'created_by' => false, 'agenda' => false, 'search' => ['role'],
            ],
        };
    }

    /** Pflichtfeld je Art beim Anlegen (null = keins). */
    protected function childRequiredField(string $kind): ?string
    {
        return match ($kind) {
            'agenda_item' => 'title',
            'document' => 'title',
            'decision' => 'text',
            default => null,
        };
    }

    /** Property-Definitionen für POST/PUT. */
    protected function childFieldSchema(string $kind): array
    {
        $owner = [
            'owner_user_id' => ['type' => 'integer', 'description' => 'Verantwortlicher (User-ID). Entweder owner_user_id oder owner_agent_id.'],
            'owner_agent_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users, type ai_user).'],
        ];

        return match ($kind) {
            'agenda_item' => [
                'position' => ['type' => 'integer', 'description' => 'Reihenfolge in der Agenda (Default 0, frei vergebbar).'],
                'title' => ['type' => 'string', 'description' => 'Titel des Agenda-Punkts.'],
                'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
                'duration_minutes' => ['type' => 'integer', 'description' => 'Geplante Dauer in Minuten.'],
                'linked_object_type' => ['type' => 'string', 'description' => 'Optional: Typ eines verknüpften Objekts (Morph-Alias, z. B. milestone). Zusammen mit linked_object_id; leerer String löst die Verknüpfung.'],
                'linked_object_id' => ['type' => 'integer', 'description' => 'Optional: ID des verknüpften Objekts.'],
                'goal' => ['type' => 'string', 'description' => 'Leitfaden 1 – Was wollten wir erreichen?'],
                'observation' => ['type' => 'string', 'description' => 'Leitfaden 2 – Was beobachten wir tatsächlich?'],
                'obstacle' => ['type' => 'string', 'description' => 'Leitfaden 3 – Was verhindert momentan die Zielerreichung?'],
                'local_solution' => ['type' => 'string', 'description' => 'Leitfaden 4a – Was kann der verantwortliche Bereich selbst lösen?'],
                'system_support' => ['type' => 'string', 'description' => 'Leitfaden 4b – Wobei braucht der Bereich Unterstützung des Gesamtsystems?'],
                'organization_entity_id' => ['type' => 'integer', 'description' => 'Optional: einbringende Einheit als Knoten der Organisation (lose).'],
                'outcome_checks' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => \Platform\ManagementBook\Enums\AgendaOutcomeCheck::values()], 'description' => 'Nach dem LA erfüllte Kriterien (ersetzt die Liste): blockers_removed, priorities_clearer, conflicts_decided, responsibilities_clear, resources_available, unit_self_reliant. Maßstab: Ist die Organisation danach handlungsfähiger?'],
            ] + $owner,
            'document' => [
                'title' => ['type' => 'string', 'description' => 'Titel der Unterlage.'],
                'type' => ['type' => 'string', 'description' => 'Freie Art (z. B. Vorlage, Präsentation, Bericht).'],
                'agenda_item_id' => ['type' => 'integer', 'description' => 'Optional: Agenda-Punkt derselben Sitzung (null löst die Zuordnung).'],
                'context_file_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Optional: IDs von Core-ContextFiles (Dateien des Teams), die mit der Unterlage verknüpft werden (context_file_references). Dateien selbst werden nicht hier gespeichert, sondern über den Core-Upload (Terminal/Files) angelegt.'],
            ],
            'minutes' => [
                'content' => ['type' => 'string', 'description' => 'Protokolltext (Markdown).'],
                'approved_at' => ['type' => 'string', 'description' => 'Freigabezeitpunkt (ISO 8601 / "YYYY-MM-DD HH:MM"; null = nicht freigegeben). Frei setzbar, auch rückwirkend; keine Freigabe-Automatik.'],
                'approved_by' => ['type' => 'integer', 'description' => 'User-ID des Freigebenden. Frei setzbar.'],
            ],
            'decision' => [
                'text' => ['type' => 'string', 'description' => 'Beschlusstext.'],
                'status' => ['type' => 'string', 'enum' => DecisionStatus::values(), 'description' => 'open (offen, Default), implemented (umgesetzt), discarded (verworfen). Frei setzbar.'],
                'agenda_item_id' => ['type' => 'integer', 'description' => 'Optional: Agenda-Punkt derselben Sitzung (null löst die Zuordnung).'],
                'due_date' => ['type' => 'string', 'description' => 'Fälligkeit (YYYY-MM-DD).'],
                'linked_task_id' => ['type' => 'integer', 'description' => 'Optional: lose Verknüpfung zu einer Planner-Aufgabe (ID, keine automatische Anlage/Synchronisation).'],
            ] + $owner,
            'attendee' => [
                'user_id' => ['type' => 'integer', 'description' => 'Teilnehmer (User-ID). Genau eines von user_id oder agent_id.'],
                'agent_id' => ['type' => 'integer', 'description' => 'Teilnehmender Agent (User-ID eines Agent-Users, type ai_user).'],
                'role' => ['type' => 'string', 'description' => 'Freie Rolle (z. B. chair, member, guest, secretary).'],
                'attended' => ['type' => 'boolean', 'description' => 'Tatsächlich anwesend gewesen (Default false). Frei setzbar, auch nachträglich.'],
            ],
        };
    }

    protected function childListSchema(string $kind): array
    {
        $cfg = $this->childConfig($kind);
        $props = [
            'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Element anhand der ID.'],
            'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            'meeting_id' => ['type' => 'integer', 'description' => 'Optional: nur Elemente dieser Sitzung.'],
        ];
        if ($cfg['agenda']) {
            $props['agenda_item_id'] = ['type' => 'integer', 'description' => 'Optional: nur Elemente dieses Agenda-Punkts.'];
        }
        if ($kind === 'decision') {
            $props['status'] = ['type' => 'string', 'enum' => DecisionStatus::values(), 'description' => 'Optional: Status filtern.'];
            $props['owner_user_id'] = ['type' => 'integer', 'description' => 'Optional: nach Verantwortlichem (User) filtern.'];
            $props['owner_agent_id'] = ['type' => 'integer', 'description' => 'Optional: nach verantwortlichem Agent filtern.'];
            $props['due_before'] = ['type' => 'string', 'description' => 'Optional: Fälligkeit bis einschließlich (YYYY-MM-DD).'];
        }
        if ($kind === 'agenda_item') {
            $props['owner_user_id'] = ['type' => 'integer', 'description' => 'Optional: nach Verantwortlichem (User) filtern.'];
            $props['owner_agent_id'] = ['type' => 'integer', 'description' => 'Optional: nach verantwortlichem Agent filtern.'];
        }
        if ($kind === 'attendee') {
            $props['user_id'] = ['type' => 'integer', 'description' => 'Optional: nur Teilnahmen dieses Users.'];
            $props['agent_id'] = ['type' => 'integer', 'description' => 'Optional: nur Teilnahmen dieses Agents.'];
            $props['attended'] = ['type' => 'boolean', 'description' => 'Optional: nach tatsächlicher Anwesenheit filtern.'];
        }
        if ($kind === 'minutes') {
            $props['approved'] = ['type' => 'boolean', 'description' => 'Optional: nur freigegebene (true) bzw. nicht freigegebene (false) Protokolle.'];
        }
        $props['search'] = ['type' => 'string', 'description' => 'Optional: Suche in ' . implode('/', $cfg['search']) . '.'];
        $props['limit'] = ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'];
        $props['offset'] = ['type' => 'integer', 'description' => 'Optional: Offset.'];

        return ['type' => 'object', 'properties' => $props];
    }

    protected function childCreateSchema(string $kind): array
    {
        $required = ['meeting_id'];
        if ($req = $this->childRequiredField($kind)) {
            $required[] = $req;
        }

        return [
            'type' => 'object',
            'properties' => array_merge($this->childFieldSchema($kind), [
                'meeting_id' => ['type' => 'integer', 'description' => 'Sitzung, zu der das Element gehört (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => $required,
        ];
    }

    protected function childUpdateSchema(string $kind): array
    {
        $props = array_merge($this->childFieldSchema($kind), [
            'id' => ['type' => 'integer', 'description' => 'ID (ERFORDERLICH).'],
            'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
        ]);
        if ($kind === 'document') {
            $props['remove_context_file_ids'] = ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Optional: IDs von ContextFiles, deren Verknüpfung zur Unterlage entfernt wird (die Datei selbst bleibt erhalten).'];
        }

        return ['type' => 'object', 'properties' => $props, 'required' => ['id']];
    }

    protected function childDeleteSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['id'],
        ];
    }

    /** @return array<string,mixed> */
    protected function childToArray(string $kind, Model $m): array
    {
        $base = ['id' => $m->getKey()];
        if (isset($m->uuid)) {
            $base['uuid'] = $m->uuid;
        }
        $base += ['team_id' => $m->team_id, 'meeting_id' => $m->meeting_id];

        $data = match ($kind) {
            'agenda_item' => [
                'position' => $m->position,
                'title' => $m->title,
                'description' => $m->description,
                'owner_user_id' => $m->owner_user_id,
                'owner_agent_id' => $m->owner_agent_id,
                'duration_minutes' => $m->duration_minutes,
                'linked_object_type' => $m->linked_object_type,
                'linked_object_id' => $m->linked_object_id,
                'goal' => $m->goal,
                'observation' => $m->observation,
                'obstacle' => $m->obstacle,
                'local_solution' => $m->local_solution,
                'system_support' => $m->system_support,
                'organization_entity_id' => $m->organization_entity_id,
                'outcome_checks' => $m->outcome_checks ?? [],
            ],
            'document' => [
                'agenda_item_id' => $m->agenda_item_id,
                'title' => $m->title,
                'type' => $m->type,
                'files' => $this->documentFiles($m),
            ],
            'minutes' => [
                'content' => $m->content,
                'approved_at' => $m->approved_at?->toIso8601String(),
                'approved_by' => $m->approved_by,
            ],
            'decision' => [
                'agenda_item_id' => $m->agenda_item_id,
                'text' => $m->text,
                'status' => $m->status?->value,
                'status_label' => $m->status?->label(),
                'owner_user_id' => $m->owner_user_id,
                'owner_agent_id' => $m->owner_agent_id,
                'due_date' => $m->due_date?->toDateString(),
                'linked_task_id' => $m->linked_task_id,
            ],
            'attendee' => [
                'user_id' => $m->user_id,
                'agent_id' => $m->agent_id,
                'role' => $m->role,
                'attended' => (bool) $m->attended,
            ],
        };

        $tail = [];
        if ($this->childConfig($kind)['created_by']) {
            $tail['created_by'] = $m->created_by;
        }
        $tail['created_at'] = $m->created_at?->toIso8601String();
        $tail['updated_at'] = $m->updated_at?->toIso8601String();

        return $base + $data + $tail;
    }

    /** @return array<int,array<string,mixed>> verknüpfte Core-ContextFiles einer Unterlage */
    protected function documentFiles(ManagementBookDocument $doc): array
    {
        return $doc->fileReferences()->with('contextFile')->ordered()->get()
            ->map(fn ($ref) => [
                'reference_id' => $ref->id,
                'context_file_id' => $ref->context_file_id,
                'file_name' => $ref->contextFile?->original_name,
                'mime_type' => $ref->contextFile?->mime_type,
                'file_size' => $ref->contextFile?->file_size,
            ])->all();
    }

    /** Sitzung im Team laden (ohne Rechteprüfung). */
    protected function findMeeting(int $id, Team $team): ?ManagementBookMeeting
    {
        return ManagementBookMeeting::query()->where('team_id', $team->id)->find($id);
    }

    /** IDs der für den User sichtbaren Sitzungen im Team (Subquery: Book -> Gremium -> Sitzung). */
    protected function visibleMeetingIds(Team $team, ToolContext $context): Builder
    {
        $committees = ManagementBookCommittee::query()->where('team_id', $team->id)
            ->whereIn('management_book_id', $this->visibleBookIds($team, $context))->select('id');

        return ManagementBookMeeting::query()->where('team_id', $team->id)->whereIn('committee_id', $committees)->select('id');
    }

    /** Prüft die Eingabe je Art; liefert eine Fehlermeldung oder null. */
    protected function validateChildInput(string $kind, array $arguments, ?Model $existing = null): ?string
    {
        foreach (['position', 'duration_minutes'] as $col) {
            if (isset($arguments[$col]) && (int) $arguments[$col] < ($col === 'position' ? 0 : 1)) {
                return $col === 'position' ? 'position darf nicht negativ sein.' : 'duration_minutes muss mindestens 1 sein.';
            }
        }
        if (isset($arguments['status']) && ! DecisionStatus::tryFrom((string) $arguments['status'])) {
            return 'status muss einer von ' . implode(', ', DecisionStatus::values()) . ' sein.';
        }
        if (array_key_exists('outcome_checks', $arguments) && $arguments['outcome_checks'] !== null) {
            if (! is_array($arguments['outcome_checks'])) {
                return 'outcome_checks muss eine Liste sein.';
            }
            $unknown = array_diff($arguments['outcome_checks'], \Platform\ManagementBook\Enums\AgendaOutcomeCheck::values());
            if ($unknown !== []) {
                return 'Unbekannte outcome_checks: ' . implode(', ', $unknown) . '.';
            }
        }
        foreach (['approved_at', 'due_date'] as $col) {
            if (! empty($arguments[$col])) {
                try {
                    Carbon::parse((string) $arguments[$col]);
                } catch (\Throwable $e) {
                    return "{$col} ist kein gültiges Datum.";
                }
            }
        }

        $userIds = [];
        foreach (['owner_user_id', 'owner_agent_id', 'approved_by', 'user_id', 'agent_id'] as $col) {
            if (! empty($arguments[$col])) {
                $userIds[] = (int) $arguments[$col];
            }
        }
        if ($kind !== 'attendee' && ! empty($arguments['owner_user_id']) && ! empty($arguments['owner_agent_id'])) {
            return 'Genau eines von owner_user_id oder owner_agent_id angeben.';
        }
        if ($kind === 'attendee') {
            $user = array_key_exists('user_id', $arguments) ? $arguments['user_id'] : $existing?->user_id;
            $agent = array_key_exists('agent_id', $arguments) ? $arguments['agent_id'] : $existing?->agent_id;
            if (empty($user) === empty($agent)) {
                return 'Genau eines von user_id oder agent_id angeben.';
            }
        }
        $userIds = array_values(array_unique($userIds));
        if ($userIds !== []) {
            $known = \Platform\Core\Models\User::whereIn('id', $userIds)->pluck('id')->map(fn ($i) => (int) $i)->all();
            if ($missing = array_diff($userIds, $known)) {
                return 'Unbekannte User-ID(s): ' . implode(', ', $missing) . '.';
            }
        }

        return null;
    }

    /** Normalisiert Datums-/Leerwerte vor dem Schreiben. */
    protected function normalizeChildFields(array $fields): array
    {
        foreach (['approved_at'] as $col) {
            if (array_key_exists($col, $fields)) {
                $fields[$col] = empty($fields[$col]) ? null : Carbon::parse((string) $fields[$col]);
            }
        }
        if (array_key_exists('due_date', $fields)) {
            $fields['due_date'] = empty($fields['due_date']) ? null : Carbon::parse((string) $fields['due_date'])->toDateString();
        }
        foreach (['title', 'text'] as $col) {
            if (isset($fields[$col])) {
                $fields[$col] = trim((string) $fields[$col]);
            }
        }
        if (array_key_exists('outcome_checks', $fields)) {
            $fields['outcome_checks'] = $fields['outcome_checks'] === null ? null : array_values(array_unique($fields['outcome_checks']));
        }
        if (array_key_exists('attended', $fields)) {
            $fields['attended'] = filter_var($fields['attended'], FILTER_VALIDATE_BOOLEAN);
        }

        return $fields;
    }

    /** Verknüpft ContextFiles (nur Dateien des Teams) mit einer Unterlage; liefert Fehlermeldung oder null. */
    protected function checkContextFiles(array $ids, Team $team): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $found = ContextFile::query()->whereIn('id', $ids)->where('team_id', $team->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if ($missing = array_diff($ids, $found)) {
            return 'ContextFile(s) nicht gefunden oder nicht im Team: ' . implode(', ', $missing) . '.';
        }

        return null;
    }

    protected function runChildList(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->childConfig($kind);
            $class = $cfg['class'];

            if (! empty($arguments['id'])) {
                $item = $class::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $item) {
                    return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
                }
                if ($denied = $this->authorizeModel($context, 'view', $item, $cfg['label'])) {
                    return $denied;
                }

                return ToolResult::success([$kind => $this->childToArray($kind, $item)]);
            }

            $query = $class::query()->where('team_id', $team->id)->whereIn('meeting_id', $this->visibleMeetingIds($team, $context));
            if (! empty($arguments['meeting_id'])) {
                $query->where('meeting_id', (int) $arguments['meeting_id']);
            }
            if ($cfg['agenda'] && ! empty($arguments['agenda_item_id'])) {
                $query->where('agenda_item_id', (int) $arguments['agenda_item_id']);
            }
            if ($kind === 'decision') {
                if (! empty($arguments['status'])) {
                    if (! DecisionStatus::tryFrom((string) $arguments['status'])) {
                        return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', DecisionStatus::values()) . ' sein.');
                    }
                    $query->where('status', $arguments['status']);
                }
                if (! empty($arguments['due_before'])) {
                    try {
                        $query->whereDate('due_date', '<=', Carbon::parse((string) $arguments['due_before'])->toDateString());
                    } catch (\Throwable $e) {
                        return ToolResult::error('VALIDATION_ERROR', 'due_before ist kein gültiges Datum.');
                    }
                }
            }
            if ($kind === 'decision' || $kind === 'agenda_item') {
                foreach (['owner_user_id', 'owner_agent_id'] as $col) {
                    if (! empty($arguments[$col])) {
                        $query->where($col, (int) $arguments[$col]);
                    }
                }
            }
            if ($kind === 'attendee') {
                foreach (['user_id', 'agent_id'] as $col) {
                    if (! empty($arguments[$col])) {
                        $query->where($col, (int) $arguments[$col]);
                    }
                }
                if (array_key_exists('attended', $arguments) && $arguments['attended'] !== null) {
                    $query->where('attended', filter_var($arguments['attended'], FILTER_VALIDATE_BOOLEAN));
                }
            }
            if ($kind === 'minutes' && array_key_exists('approved', $arguments) && $arguments['approved'] !== null) {
                filter_var($arguments['approved'], FILTER_VALIDATE_BOOLEAN) ? $query->whereNotNull('approved_at') : $query->whereNull('approved_at');
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(function ($q) use ($cfg, $term) {
                    foreach ($cfg['search'] as $col) {
                        $q->orWhere($col, 'like', $term);
                    }
                });
            }

            $total = (clone $query)->count();
            [$limit, $offset] = $this->pageArgs($arguments);
            match ($kind) {
                'agenda_item' => $query->orderBy('meeting_id')->orderBy('position'),
                'decision' => $query->orderBy('meeting_id')->orderBy('due_date'),
                default => $query->orderBy('meeting_id'),
            };
            $rows = $query->orderBy('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                $cfg['plural'] => $rows->map(fn ($m) => $this->childToArray($kind, $m))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden: ' . $e->getMessage());
        }
    }

    protected function runChildCreate(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->childConfig($kind);
            $required = $this->childRequiredField($kind);
            if (empty($arguments['meeting_id']) || ($required && trim((string) ($arguments[$required] ?? '')) === '')) {
                return ToolResult::error('VALIDATION_ERROR', 'meeting_id' . ($required ? " und {$required}" : '') . ' sind erforderlich.');
            }
            $meeting = $this->findMeeting((int) $arguments['meeting_id'], $team);
            if (! $meeting) {
                return ToolResult::error('NOT_FOUND', 'Sitzung nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $meeting, 'Sitzung')) {
                return $denied;
            }
            if ($message = $this->validateChildInput($kind, $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }
            $fileIds = $kind === 'document' ? (array) ($arguments['context_file_ids'] ?? []) : [];
            if ($fileIds !== [] && ($message = $this->checkContextFiles($fileIds, $team))) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $data = $this->normalizeChildFields(array_intersect_key($arguments, array_flip($cfg['fields'])));
            $data['meeting_id'] = $meeting->id;
            $data['team_id'] = $team->id;
            if ($cfg['created_by']) {
                $data['created_by'] = $context->user->id;
            }

            $class = $cfg['class'];
            $item = $class::create($data);
            foreach (array_unique(array_map('intval', $fileIds)) as $fileId) {
                $item->addFileReference($fileId);
            }

            return ToolResult::success([
                $kind => $this->childToArray($kind, $item->fresh()),
                'message' => "{$cfg['label']} #{$item->getKey()} in Sitzung #{$meeting->id} angelegt.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return ToolResult::error('VALIDATION_ERROR', 'Dieser Teilnehmer ist der Sitzung bereits zugeordnet.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    protected function runChildUpdate(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->childConfig($kind);
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }
            $class = $cfg['class'];
            $item = $class::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $item) {
                return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
            }
            if ($denied = $this->authorizeModel($context, 'update', $item, $cfg['label'])) {
                return $denied;
            }
            $required = $this->childRequiredField($kind);
            if ($required && array_key_exists($required, $arguments) && trim((string) $arguments[$required]) === '') {
                return ToolResult::error('VALIDATION_ERROR', "{$required} darf nicht leer sein.");
            }
            if ($message = $this->validateChildInput($kind, $arguments, $item)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }
            $addIds = $kind === 'document' ? (array) ($arguments['context_file_ids'] ?? []) : [];
            if ($addIds !== [] && ($message = $this->checkContextFiles($addIds, $team))) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fields = $this->normalizeChildFields(array_intersect_key($arguments, array_flip($cfg['fields'])));
            if ($fields !== []) {
                $item->update($fields);
            }
            if ($kind === 'document') {
                foreach (array_unique(array_map('intval', $addIds)) as $fileId) {
                    $item->addFileReference($fileId);
                }
                foreach (array_unique(array_map('intval', (array) ($arguments['remove_context_file_ids'] ?? []))) as $fileId) {
                    $item->fileReferences()->where('context_file_id', $fileId)->delete();
                }
            }

            return ToolResult::success([
                $kind => $this->childToArray($kind, $item->fresh()),
                'message' => "{$cfg['label']} #{$item->getKey()} aktualisiert.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return ToolResult::error('VALIDATION_ERROR', 'Dieser Teilnehmer ist der Sitzung bereits zugeordnet.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    protected function runChildDelete(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->childConfig($kind);
            $class = $cfg['class'];
            $item = $class::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $item) {
                return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
            }
            if ($denied = $this->authorizeModel($context, 'delete', $item, $cfg['label'])) {
                return $denied;
            }

            $id = $item->getKey();
            $soft = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($item), true);
            $item->delete();

            return ToolResult::success([
                'id' => $id,
                'message' => "{$cfg['label']} #{$id} gelöscht" . ($soft ? ' (Soft-Delete).' : '.'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }
}
