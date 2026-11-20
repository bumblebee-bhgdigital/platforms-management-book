<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetings;

class UpdateMeetingTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetings;

    public function getName(): string
    {
        return 'management-book.meetings.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/meetings/{id} - Ändert eine Sitzung (verschieben, Ort ändern, Status setzen, absagen mit status=cancelled). Pflicht: id. '
            . 'Felder: title, scheduled_at, duration_minutes, location, external_calendar_ref, status (planned/prepared/held/cancelled). Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->meetingFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID der Sitzung (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }
            $meeting = ManagementBookMeeting::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $meeting) {
                return ToolResult::error('NOT_FOUND', 'Sitzung nicht gefunden.');
            }
            if ($denied = $this->authorizeMeeting($context, 'update', $meeting)) {
                return $denied;
            }
            if (array_key_exists('title', $arguments) && trim((string) $arguments['title']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
            }
            if ($message = $this->validateMeetingInput($arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fields = array_intersect_key($arguments, array_flip(['title', 'scheduled_at', 'duration_minutes', 'location', 'external_calendar_ref', 'status']));
            if (isset($fields['scheduled_at'])) {
                $fields['scheduled_at'] = \Illuminate\Support\Carbon::parse((string) $fields['scheduled_at']);
            }
            if ($fields !== []) {
                $meeting->update($fields);
            }

            return ToolResult::success([
                'meeting' => $this->meetingToArray($meeting->fresh()),
                'message' => "Sitzung #{$meeting->id} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'meetings', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
