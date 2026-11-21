<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Tools\Concerns\HandlesRisks;

class DeleteRiskTool implements ToolContract, ToolMetadataContract
{
    use HandlesRisks;

    public function getName(): string
    {
        return 'management-book.risks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/risks/{id} - Löscht ein Risiko (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Zum Abschließen ohne Löschen stattdessen management-book.risks.PUT mit status=closed nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Risikos (ERFORDERLICH).'],
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
            $risk = ManagementBookRisk::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $risk) {
                return ToolResult::error('NOT_FOUND', 'Risiko nicht gefunden.');
            }
            if ($denied = $this->authorizeRisk($context, 'delete', $risk)) {
                return $denied;
            }

            $id = $risk->id;
            $title = $risk->title;
            $risk->delete();

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Risiko '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->riskMetadata('DELETE');
    }
}
