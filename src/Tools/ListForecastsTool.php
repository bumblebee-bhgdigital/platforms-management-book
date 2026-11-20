<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Tools\Concerns\HandlesForecasts;

class ListForecastsTool implements ToolContract, ToolMetadataContract
{
    use HandlesForecasts;

    public function getName(): string
    {
        return 'management-book.forecasts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/forecasts - Listet Forecasts (Regnosen mit Zieljahr; nur sichtbare Books), nach Zieldatum sortiert. '
            . 'Einzelner Forecast: id=<id> (mit include_versions=true inkl. Versionshistorie). Filter: management_book_id, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelner Forecast anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Forecasts dieses Management Books.'],
                'include_versions' => ['type' => 'boolean', 'description' => 'Optional (nur mit id): Versionshistorie mitliefern.'],
                'include_content' => ['type' => 'boolean', 'description' => 'Optional (Default true): Markdown-Inhalt der aktuellen Version mitliefern. In Listen auf false setzen für kompakte Sicht.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche im Titel.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $withContent = array_key_exists('include_content', $arguments) ? (bool) $arguments['include_content'] : true;

            if (! empty($arguments['id'])) {
                $forecast = $this->findForecast((int) $arguments['id'], $team);
                if (! $forecast) {
                    return ToolResult::error('NOT_FOUND', 'Forecast nicht gefunden.');
                }
                if ($denied = $this->authorizeModel($context, 'view', $forecast, 'Forecast')) {
                    return $denied;
                }
                $data = $this->forecastToArray($forecast, $withContent);
                if (! empty($arguments['include_versions'])) {
                    $data['versions'] = $forecast->versions->map(fn ($v) => [
                        'id' => $v->id,
                        'version' => $v->version,
                        'change_note' => $v->change_note,
                        'content' => $withContent ? $v->content : null,
                        'created_by' => $v->created_by,
                        'created_at' => $v->created_at?->toIso8601String(),
                    ])->all();
                }

                return ToolResult::success(['forecast' => $data]);
            }

            $query = ManagementBookForecast::query()->where('team_id', $team->id)
                ->whereIn('management_book_id', $this->visibleBookIds($team, $context));
            if (! empty($arguments['management_book_id'])) {
                $query->where('management_book_id', (int) $arguments['management_book_id']);
            }
            if (! empty($arguments['search'])) {
                $query->where('title', 'like', '%' . $arguments['search'] . '%');
            }

            $total = (clone $query)->count();
            [$limit, $offset] = $this->pageArgs($arguments);
            $rows = $query->with('currentVersion')->orderBy('target_date')->orderBy('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'forecasts' => $rows->map(fn ($f) => $this->forecastToArray($f, $withContent))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Forecasts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('forecasts', 'GET');
    }
}
