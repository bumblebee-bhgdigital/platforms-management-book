<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetings;

class ListMeetingsTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetings;

    public function getName(): string
    {
        return 'management-book.meetings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/meetings - Listet Sitzungen von Gremien (nur sichtbare), nach Termin sortiert. Einzelne Sitzung: id=<meeting_id>. '
            . 'Filter: committee_id, management_book_id, status, from/to (Datum), search, limit/offset. '
            . 'Nächste Sitzung eines Gremiums: committee_id + next=true (liefert die nächste anstehende, nicht abgesagte Sitzung).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelne Sitzung anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'committee_id' => ['type' => 'integer', 'description' => 'Optional: nur Sitzungen dieses Gremiums.'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Sitzungen von Gremien dieses Management Books.'],
                'status' => ['type' => 'string', 'enum' => MeetingStatus::values(), 'description' => 'Optional: Status-Filter.'],
                'next' => ['type' => 'boolean', 'description' => 'Optional: nur die nächste anstehende Sitzung (je Gremium bei committee_id, sonst insgesamt).'],
                'from' => ['type' => 'string', 'description' => 'Optional: Termine ab diesem Datum.'],
                'to' => ['type' => 'string', 'description' => 'Optional: Termine bis einschließlich diesem Datum.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel/Ort.'],
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
                $meeting = ManagementBookMeeting::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $meeting) {
                    return ToolResult::error('NOT_FOUND', 'Sitzung nicht gefunden.');
                }
                if ($denied = $this->authorizeMeeting($context, 'view', $meeting)) {
                    return $denied;
                }

                return ToolResult::success(['meeting' => $this->meetingToArray($meeting)]);
            }

            $visibleBooks = ManagementBook::query()->where('team_id', $team->id)->visibleTo($context->user)->select('id');
            $visibleCommittees = ManagementBookCommittee::query()->where('team_id', $team->id)->whereIn('management_book_id', $visibleBooks)->select('id');
            $query = ManagementBookMeeting::query()->where('team_id', $team->id)->whereIn('committee_id', $visibleCommittees);

            if (! empty($arguments['committee_id'])) {
                $query->where('committee_id', (int) $arguments['committee_id']);
            }
            if (! empty($arguments['management_book_id'])) {
                $query->whereIn('committee_id', ManagementBookCommittee::query()->where('management_book_id', (int) $arguments['management_book_id'])->select('id'));
            }
            if (! empty($arguments['status'])) {
                if (! MeetingStatus::tryFrom((string) $arguments['status'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', MeetingStatus::values()) . ' sein.');
                }
                $query->where('status', $arguments['status']);
            }
            try {
                if (! empty($arguments['from'])) {
                    $query->where('scheduled_at', '>=', \Illuminate\Support\Carbon::parse((string) $arguments['from'])->startOfDay());
                }
                if (! empty($arguments['to'])) {
                    $query->where('scheduled_at', '<=', \Illuminate\Support\Carbon::parse((string) $arguments['to'])->endOfDay());
                }
            } catch (\Throwable $e) {
                return ToolResult::error('VALIDATION_ERROR', 'from/to sind kein gültiges Datum.');
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('location', 'like', $term));
            }

            if (! empty($arguments['next'])) {
                $query->upcoming();
                $meeting = $query->orderBy('scheduled_at')->first();

                return ToolResult::success([
                    'meeting' => $meeting ? $this->meetingToArray($meeting) : null,
                    'team_id' => $team->id,
                ]);
            }

            $total = (clone $query)->count();
            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $meetings = $query->orderBy('scheduled_at')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'meetings' => $meetings->map(fn ($m) => $this->meetingToArray($m))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Sitzungen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['management-book', 'meetings', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
