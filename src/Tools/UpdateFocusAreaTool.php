<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreas;

class UpdateFocusAreaTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreas;

    public function getName(): string
    {
        return 'management-book.focus_areas.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/focus-areas/{id} - Ändert einen Fokusraum. Pflicht: id. '
            . 'Felder: title, description, content, central_question_vision_images/_obstacles/_milestones, order, forecast_id. Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->focusAreaFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID des Fokusraums (ERFORDERLICH).'],
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
            $fa = $this->findFocusArea((int) $arguments['id'], $team);
            if (! $fa) {
                return ToolResult::error('NOT_FOUND', 'Fokusraum nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $fa, 'Fokusraum')) {
                return $denied;
            }
            if (array_key_exists('title', $arguments) && trim((string) $arguments['title']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
            }
            if ($message = $this->validateForecastLink($arguments['forecast_id'] ?? null, (int) $fa->management_book_id, $team)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fields = array_intersect_key($arguments, array_flip([
                'title', 'description', 'content',
                'central_question_vision_images', 'central_question_obstacles', 'central_question_milestones',
                'order', 'forecast_id',
            ]));
            if (isset($fields['title'])) {
                $fields['title'] = trim($fields['title']);
            }
            if (array_key_exists('forecast_id', $fields)) {
                $fields['forecast_id'] = ! empty($fields['forecast_id']) ? (int) $fields['forecast_id'] : null;
            }
            if ($fields !== []) {
                $fa->update($fields);
            }

            return ToolResult::success([
                'focus_area' => $this->focusAreaToArray($fa->fresh()),
                'message' => "Fokusraum #{$fa->id} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('focus_areas', 'PUT');
    }
}
