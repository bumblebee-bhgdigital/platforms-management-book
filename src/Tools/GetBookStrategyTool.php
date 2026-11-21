<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Tools\Concerns\HandlesStrategy;

/**
 * Kombinierte Sicht auf die Strategie eines Management Books (Gegenstück zu organization.strategy.GET):
 * Mission/Vision + Forecasts + Fokusräume + Zielbilder + Hindernisse + Meilensteine + Transformation Map.
 */
class GetBookStrategyTool implements ToolContract, ToolMetadataContract
{
    use HandlesStrategy;

    public function getName(): string
    {
        return 'management-book.strategy.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/books/{id}/strategy - Kombinierte Sicht auf die Strategie eines Management Books: Mission/Vision + Forecasts (mit Zieljahr) + '
            . 'Fokusräume (je mit Zielbildern, Hindernissen, Meilensteinen und den drei Leitfragen) + Transformation Map (Meilensteine nach Jahr/Quartal). '
            . 'Argumente: management_book_id (pflicht), include_content (default true; lädt Markdown von Mission/Vision/Forecast/Fokusraum). '
            . 'Ersetzt viele Einzel-Calls; Änderungen über management-book.strategy.PUT, forecasts/focus_areas/vision_images/obstacles/milestones.*.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'management_book_id' => ['type' => 'integer', 'description' => 'PFLICHT. ID des Management Books.'],
                'include_content' => ['type' => 'boolean', 'description' => 'Optional (default true): Markdown-Inhalte mitladen. Auf false für eine kompakte Struktur-Sicht.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['management_book_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'view');
            if ($error) {
                return $error;
            }
            $withContent = array_key_exists('include_content', $arguments) ? (bool) $arguments['include_content'] : true;

            $mission = ManagementBookStrategicDocument::activeFor($book->id, StrategicDocumentType::MISSION);
            $vision = ManagementBookStrategicDocument::activeFor($book->id, StrategicDocumentType::VISION);

            $forecasts = ManagementBookForecast::query()->where('management_book_id', $book->id)
                ->with('currentVersion')->orderBy('target_date')->orderBy('id')->get();

            $focusAreas = ManagementBookFocusArea::query()->where('management_book_id', $book->id)
                ->with([
                    'visionImages' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                    'obstacles' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                    'milestones' => fn ($q) => $q->orderBy('target_year')->orderBy('target_quarter')->orderBy('order')->orderBy('id'),
                ])
                ->orderBy('order')->orderBy('id')->get();

            $totals = ['forecasts' => $forecasts->count(), 'focus_areas' => $focusAreas->count(), 'vision_images' => 0, 'obstacles' => 0, 'milestones' => 0];
            $years = [];
            $grid = [];
            $noYear = [];

            $focusAreaData = $focusAreas->map(function (ManagementBookFocusArea $fa) use (&$totals, &$years, &$grid, &$noYear, $withContent) {
                $milestones = $fa->milestones->map(fn ($m) => $this->itemToArray($m))->values()->all();
                foreach ($milestones as $m) {
                    if ($m['target_year']) {
                        $years[$m['target_year']] = true;
                        $grid[$fa->id][$m['target_year']][] = $m;
                    } else {
                        $noYear[$fa->id][] = $m;
                    }
                }
                $totals['vision_images'] += $fa->visionImages->count();
                $totals['obstacles'] += $fa->obstacles->count();
                $totals['milestones'] += count($milestones);

                return array_merge($this->focusAreaToArray($fa, $withContent), [
                    'vision_images' => $fa->visionImages->map(fn ($m) => $this->itemToArray($m))->values()->all(),
                    'obstacles' => $fa->obstacles->map(fn ($m) => $this->itemToArray($m))->values()->all(),
                    'milestones' => $milestones,
                ]);
            })->values()->all();
            ksort($years);

            $forecastData = $forecasts->map(fn (ManagementBookForecast $f) => array_merge($this->forecastToArray($f, $withContent), [
                'focus_area_ids' => $focusAreas->where('forecast_id', $f->id)->pluck('id')->values()->all(),
            ]))->values()->all();

            $result = [
                'book' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'status' => $book->status?->value,
                    'horizon_year' => $book->horizon_year,
                ],
                'mission' => $mission ? $this->documentToArray($mission, $withContent) : null,
                'vision' => $vision ? $this->documentToArray($vision, $withContent) : null,
                'chapters' => collect(StrategicDocumentType::cases())
                    ->reject(fn ($t) => in_array($t, [StrategicDocumentType::MISSION, StrategicDocumentType::VISION], true))
                    ->mapWithKeys(function ($t) use ($book, $withContent) {
                        $doc = ManagementBookStrategicDocument::activeFor($book->id, $t);

                        return [$t->value => $doc ? $this->documentToArray($doc, $withContent) : null];
                    })->all(),
                'forecasts' => $forecastData,
                'focus_areas' => $focusAreaData,
                'transformation_map' => [
                    'years' => array_keys($years),
                    'grid' => $grid,
                    'no_year' => $noYear,
                ],
                'totals' => $totals,
            ];
            if ($totals['focus_areas'] > ManagementBookFocusArea::RECOMMENDED_MAX) {
                $result['hint'] = "Das Book hat {$totals['focus_areas']} Fokusräume; empfohlen sind 5–7.";
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Strategie: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('strategy', 'GET');
    }
}
