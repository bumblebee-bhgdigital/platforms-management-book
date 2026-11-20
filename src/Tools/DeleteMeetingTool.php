<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetings;

class DeleteMeetingTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetings;

    public function getName(): string
    {
        return 'management-book.meetings.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/meetings/{id} - Löscht eine Sitzung (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Zum Absagen ohne Löschen stattdessen management-book.meetings.PUT mit status=cancelled nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Sitzung (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
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
            $meeting = ManagementBookMeeting::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $meeting) {
                return ToolResult::error('NOT_FOUND', 'Sitzung nicht gefunden.');
            }
            if ($denied = $this->authorizeMeeting($context, 'delete', $meeting)) {
                return $denied;
            }

            $title = $meeting->title;
            $id = $meeting->id;
            $meeting->delete();

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Sitzung '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'meetings', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
