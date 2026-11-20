<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Tools\Concerns\HandlesForecasts;

class CreateForecastTool implements ToolContract, ToolMetadataContract
{
    use HandlesForecasts;

    public function getName(): string
    {
        return 'management-book.forecasts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/forecasts - Legt einen Forecast (Regnose, versionierbar) an einem Management Book an. '
            . 'Pflicht: management_book_id, title und target_date (YYYY-MM-DD) ODER target_year (= 31.12. des Jahres). '
            . 'Optional: content (Markdown); mit create_initial_version=true wird daraus direkt Version 1 angelegt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book (ERFORDERLICH).'],
                'title' => ['type' => 'string', 'description' => 'Titel des Forecasts (ERFORDERLICH).'],
                'target_date' => ['type' => 'string', 'description' => 'Zieldatum YYYY-MM-DD.'],
                'target_year' => ['type' => 'integer', 'description' => 'Alternative zu target_date: Zieljahr (wird als 31.12. gespeichert).'],
                'content' => ['type' => 'string', 'description' => 'Optional: Markdown-Inhalt.'],
                'create_initial_version' => ['type' => 'boolean', 'description' => 'Optional: aus content direkt Version 1 anlegen und als aktuelle markieren.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
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
                return ToolResult::error('VALIDATION_ERROR', 'management_book_id, title und target_date (oder target_year) sind erforderlich.');
            }
            [$targetDate, $dateError] = $this->resolveTargetDate($arguments);
            if ($dateError || ! $targetDate) {
                return ToolResult::error('VALIDATION_ERROR', $dateError ?? 'target_date (oder target_year) ist erforderlich.');
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'update');
            if ($error) {
                return $error;
            }

            $forecast = ManagementBookForecast::create([
                'team_id' => $team->id,
                'management_book_id' => $book->id,
                'title' => trim($arguments['title']),
                'target_date' => $targetDate,
                'content' => $arguments['content'] ?? null,
                'created_by' => $context->user->id,
            ]);

            if (! empty($arguments['create_initial_version']) && ! empty($arguments['content'])) {
                $forecast->createNewVersion((string) $arguments['content'], 'Initial version', $context->user->id);
            }

            return ToolResult::success([
                'forecast' => $this->forecastToArray($forecast->fresh('currentVersion')),
                'message' => "Forecast '{$forecast->title}' (#{$forecast->id}) an Management Book #{$book->id} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('forecasts', 'POST');
    }
}
