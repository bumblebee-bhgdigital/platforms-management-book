<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreas;

class CreateFocusAreaTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreas;

    public function getName(): string
    {
        return 'management-book.focus_areas.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/focus-areas - Legt einen Fokusraum an einem Management Book an (empfohlen 5–7 je Book; bei mehr kommt nur ein Hinweis, kein Fehler). '
            . 'Pflicht: management_book_id, title. Optional: description, content, die drei Leitfragen (central_question_vision_images/_obstacles/_milestones), order, forecast_id (Soft-Link).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->focusAreaFieldSchema(), [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['management_book_id', 'title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (trim((string) ($arguments['title'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'management_book_id und title sind erforderlich.');
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'update');
            if ($error) {
                return $error;
            }
            if ($message = $this->validateForecastLink($arguments['forecast_id'] ?? null, $book->id, $team)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fa = ManagementBookFocusArea::create([
                'team_id' => $team->id,
                'management_book_id' => $book->id,
                'forecast_id' => ! empty($arguments['forecast_id']) ? (int) $arguments['forecast_id'] : null,
                'title' => trim($arguments['title']),
                'description' => $arguments['description'] ?? null,
                'content' => $arguments['content'] ?? null,
                'central_question_vision_images' => $arguments['central_question_vision_images'] ?? null,
                'central_question_obstacles' => $arguments['central_question_obstacles'] ?? null,
                'central_question_milestones' => $arguments['central_question_milestones'] ?? null,
                'order' => (int) ($arguments['order'] ?? 0),
                'created_by' => $context->user->id,
            ]);

            $result = [
                'focus_area' => $this->focusAreaToArray($fa->fresh()),
                'message' => "Fokusraum '{$fa->title}' (#{$fa->id}) an Management Book #{$book->id} angelegt.",
            ];
            $count = ManagementBookFocusArea::query()->where('management_book_id', $book->id)->count();
            if ($count > ManagementBookFocusArea::RECOMMENDED_MAX) {
                $result['hint'] = "Das Book hat jetzt {$count} Fokusräume; empfohlen sind 5–7.";
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('focus_areas', 'POST');
    }
}
