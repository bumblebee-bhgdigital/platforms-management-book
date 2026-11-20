<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesForecasts;

class CreateForecastVersionTool implements ToolContract, ToolMetadataContract
{
    use HandlesForecasts;

    public function getName(): string
    {
        return 'management-book.forecasts.versions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/forecasts/{forecast_id}/versions - Legt eine neue Version (Regnose) des Forecast-Inhalts an und setzt sie als aktuelle Version. '
            . 'Der bisherige Inhalt bleibt in den früheren Versionen erhalten (Historie, abrufbar über forecasts.GET mit include_versions). Pflicht: forecast_id, content.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'forecast_id' => ['type' => 'integer', 'description' => 'Forecast (ERFORDERLICH).'],
                'content' => ['type' => 'string', 'description' => 'Neuer Markdown-Inhalt (ERFORDERLICH).'],
                'change_note' => ['type' => 'string', 'description' => 'Optional: Grund der Änderung.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['forecast_id', 'content'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['forecast_id']) || trim((string) ($arguments['content'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'forecast_id und content sind erforderlich.');
            }
            $forecast = $this->findForecast((int) $arguments['forecast_id'], $team);
            if (! $forecast) {
                return ToolResult::error('NOT_FOUND', 'Forecast nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $forecast, 'Forecast')) {
                return $denied;
            }

            $version = $forecast->createNewVersion((string) $arguments['content'], $arguments['change_note'] ?? null, $context->user->id);
            $forecast->refresh();

            return ToolResult::success([
                'forecast_id' => $forecast->id,
                'version_id' => $version->id,
                'version' => $version->version,
                'current_version_id' => $forecast->current_version_id,
                'message' => "Version {$version->version} des Forecasts #{$forecast->id} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Version: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('forecasts', 'POST');
    }
}
