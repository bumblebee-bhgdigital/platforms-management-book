<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreas;

class DeleteFocusAreaTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreas;

    public function getName(): string
    {
        return 'management-book.focus_areas.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/focus-areas/{id} - Löscht einen Fokusraum (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Wie in der Organization werden Zielbilder, Hindernisse und Meilensteine nicht mitgelöscht, sondern bei Bedarf separat gelöscht; sie verschwinden aber aus management-book.strategy.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Fokusraums (ERFORDERLICH).'],
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
            $fa = $this->findFocusArea((int) ($arguments['id'] ?? 0), $team);
            if (! $fa) {
                return ToolResult::error('NOT_FOUND', 'Fokusraum nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'delete', $fa, 'Fokusraum')) {
                return $denied;
            }

            $id = $fa->id;
            $title = $fa->title;
            $fa->delete();

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Fokusraum '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('focus_areas', 'DELETE');
    }
}
