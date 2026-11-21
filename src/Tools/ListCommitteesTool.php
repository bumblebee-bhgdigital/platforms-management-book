<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Tools\Concerns\HandlesCommittees;

class ListCommitteesTool implements ToolContract, ToolMetadataContract
{
    use HandlesCommittees;

    public function getName(): string
    {
        return 'management-book.committees.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/committees - Listet Gremien eines Management Books (nur sichtbare). Einzelnes Gremium: id=<committee_id>. '
            . 'Sonst Filter: management_book_id, type, status, search, limit/offset. Das Gremium IST ein Organisations-Knoten: name, description, members und chair kommen von dort (identity_source/members_source = organization; Mitglieder aus den Beziehungen is_part_of/manages/is_active_in, auch Personen ohne Konto). Liefert organization_node (Knoten mit URL) '
            . 'und das Format des Gremientyps (bei id=… vollständig: agenda_kinds, agenda_fields, decision_kinds, outcome.checks – damit Agenten Punkte und Beschlüsse passend anlegen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Gremium anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Gremien dieses Management Books.'],
                'type' => ['type' => 'string', 'enum' => CommitteeType::values(), 'description' => 'Optional: Typ-Filter.'],
                'status' => ['type' => 'string', 'enum' => CommitteeStatus::values(), 'description' => 'Optional: Status-Filter.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Name/Beschreibung.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }

            if (! empty($arguments['id'])) {
                $committee = ManagementBookCommittee::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $committee) {
                    return ToolResult::error('NOT_FOUND', 'Gremium nicht gefunden.');
                }
                if ($denied = $this->authorizeCommittee($context, 'view', $committee)) {
                    return $denied;
                }

                return ToolResult::success(['committee' => $this->committeeToArray($committee, true)]);
            }

            $query = ManagementBookCommittee::query()->where('team_id', $team->id)
                ->whereIn('management_book_id', ManagementBook::query()->where('team_id', $team->id)->visibleTo($context->user)->select('id'));

            if (! empty($arguments['management_book_id'])) {
                $query->where('management_book_id', (int) $arguments['management_book_id']);
            }
            if (! empty($arguments['type'])) {
                if (! CommitteeType::tryFrom((string) $arguments['type'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'type muss einer von ' . implode(', ', CommitteeType::values()) . ' sein.');
                }
                $query->where('type', $arguments['type']);
            }
            if (! empty($arguments['status'])) {
                if (! CommitteeStatus::tryFrom((string) $arguments['status'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', CommitteeStatus::values()) . ' sein.');
                }
                $query->where('status', $arguments['status']);
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $nodeIds = class_exists(\Platform\Organization\Models\OrganizationEntity::class)
                    ? \Platform\Organization\Models\OrganizationEntity::where('name', 'like', $term)->pluck('id')->all() : [];
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term)->orWhereIn('organization_entity_id', $nodeIds));
            }

            $total = (clone $query)->count();
            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $committees = $query->with(['members', 'organizationNode'])->orderBy('name')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'committees' => $committees->map(fn ($c) => $this->committeeToArray($c))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Gremien: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['management-book', 'committees', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
