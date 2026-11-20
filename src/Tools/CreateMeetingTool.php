<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetings;

class CreateMeetingTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetings;

    public function getName(): string
    {
        return 'management-book.meetings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/meetings - Legt eine Sitzung (Termin) an einem Gremium an. Pflicht: committee_id, title, scheduled_at. '
            . 'Optional: duration_minutes, location (Ort/Link), external_calendar_ref, status (Default planned). '
            . 'Keine Automatik und keine Kalender-Synchronisation.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->meetingFieldSchema(), [
                'committee_id' => ['type' => 'integer', 'description' => 'Gremium, zu dem die Sitzung gehört (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['committee_id', 'title', 'scheduled_at'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['committee_id']) || trim((string) ($arguments['title'] ?? '')) === '' || empty($arguments['scheduled_at'])) {
                return ToolResult::error('VALIDATION_ERROR', 'committee_id, title und scheduled_at sind erforderlich.');
            }
            $committee = ManagementBookCommittee::query()->where('team_id', $team->id)->find((int) $arguments['committee_id']);
            if (! $committee) {
                return ToolResult::error('NOT_FOUND', 'Gremium nicht gefunden.');
            }
            if (! Gate::forUser($context->user)->allows('update', $committee)) {
                return ToolResult::error('ACCESS_DENIED', "Keine Berechtigung, Sitzungen an Gremium #{$committee->id} anzulegen.");
            }
            if ($message = $this->validateMeetingInput($arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $meeting = ManagementBookMeeting::create([
                'team_id' => $team->id,
                'committee_id' => $committee->id,
                'title' => trim($arguments['title']),
                'scheduled_at' => \Illuminate\Support\Carbon::parse((string) $arguments['scheduled_at']),
                'duration_minutes' => $arguments['duration_minutes'] ?? null,
                'location' => $arguments['location'] ?? null,
                'external_calendar_ref' => $arguments['external_calendar_ref'] ?? null,
                'status' => $arguments['status'] ?? null,
                'created_by' => $context->user->id,
            ]);

            return ToolResult::success([
                'meeting' => $this->meetingToArray($meeting->fresh()),
                'message' => "Sitzung '{$meeting->title}' (#{$meeting->id}) an Gremium #{$committee->id} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'meetings', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
