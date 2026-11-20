<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Tools\Concerns\HandlesCommittees;

class DeleteCommitteeTool implements ToolContract, ToolMetadataContract
{
    use HandlesCommittees;

    public function getName(): string
    {
        return 'management-book.committees.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/committees/{id} - Löscht ein Gremium (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Zum Beenden ohne Löschen stattdessen management-book.committees.PUT mit status=dissolved nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Gremiums (ERFORDERLICH).'],
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
            $committee = ManagementBookCommittee::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $committee) {
                return ToolResult::error('NOT_FOUND', 'Gremium nicht gefunden.');
            }
            if ($denied = $this->authorizeCommittee($context, 'delete', $committee)) {
                return $denied;
            }

            $name = $committee->name;
            $id = $committee->id;
            $committee->delete();

            return ToolResult::success([
                'id' => $id,
                'name' => $name,
                'message' => "Gremium '{$name}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'committees', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
