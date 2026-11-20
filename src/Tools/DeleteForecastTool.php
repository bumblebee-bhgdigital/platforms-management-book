<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Tools\Concerns\HandlesForecasts;

class DeleteForecastTool implements ToolContract, ToolMetadataContract
{
    use HandlesForecasts;

    public function getName(): string
    {
        return 'management-book.forecasts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/forecasts/{id} - Löscht einen Forecast (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Fokusräume bleiben bestehen (der Forecast-Link ist optional) und verlieren nur die Verknüpfung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Forecasts (ERFORDERLICH).'],
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
            $forecast = $this->findForecast((int) ($arguments['id'] ?? 0), $team);
            if (! $forecast) {
                return ToolResult::error('NOT_FOUND', 'Forecast nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'delete', $forecast, 'Forecast')) {
                return $denied;
            }

            $id = $forecast->id;
            $title = $forecast->title;
            $forecast->delete();
            ManagementBookFocusArea::query()->where('forecast_id', $id)->update(['forecast_id' => null]);

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Forecast '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('forecasts', 'DELETE');
    }
}
