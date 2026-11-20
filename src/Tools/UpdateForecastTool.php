<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesForecasts;

class UpdateForecastTool implements ToolContract, ToolMetadataContract
{
    use HandlesForecasts;

    public function getName(): string
    {
        return 'management-book.forecasts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/forecasts/{id} - Ändert einen Forecast (title, target_date/target_year, content). Pflicht: id. '
            . 'Inhaltsänderungen bevorzugt über management-book.forecasts.versions.POST (versioniert); content hier ist ein Direkt-Update ohne neue Version.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Forecasts (ERFORDERLICH).'],
                'title' => ['type' => 'string', 'description' => 'Titel.'],
                'target_date' => ['type' => 'string', 'description' => 'Zieldatum YYYY-MM-DD.'],
                'target_year' => ['type' => 'integer', 'description' => 'Alternative zu target_date: Zieljahr (31.12.).'],
                'content' => ['type' => 'string', 'description' => 'Direkt-Update des Inhalts ohne Versionierung.'],
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
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }
            $forecast = $this->findForecast((int) $arguments['id'], $team);
            if (! $forecast) {
                return ToolResult::error('NOT_FOUND', 'Forecast nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $forecast, 'Forecast')) {
                return $denied;
            }
            if (array_key_exists('title', $arguments) && trim((string) $arguments['title']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
            }
            [$targetDate, $dateError] = $this->resolveTargetDate($arguments);
            if ($dateError) {
                return ToolResult::error('VALIDATION_ERROR', $dateError);
            }

            $fields = array_intersect_key($arguments, array_flip(['title', 'content']));
            if (isset($fields['title'])) {
                $fields['title'] = trim($fields['title']);
            }
            if ($targetDate) {
                $fields['target_date'] = $targetDate;
            }
            if ($fields !== []) {
                $forecast->update($fields);
            }

            return ToolResult::success([
                'forecast' => $this->forecastToArray($forecast->fresh('currentVersion')),
                'message' => "Forecast #{$forecast->id} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('forecasts', 'PUT');
    }
}
