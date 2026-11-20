<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreas;

class ListFocusAreasTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreas;

    public function getName(): string
    {
        return 'management-book.focus_areas.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/focus-areas - Listet Fokusräume (empfohlen 5–7 je Management Book; nur sichtbare Books), nach Reihenfolge sortiert. '
            . 'Einzelner Fokusraum: id=<id> (inkl. Zielbilder, Hindernisse, Meilensteine). Filter: management_book_id, forecast_id, search, limit/offset. '
            . 'Gesamtbild eines Books: management-book.strategy.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelner Fokusraum anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Fokusräume dieses Management Books.'],
                'forecast_id' => ['type' => 'integer', 'description' => 'Optional: nur Fokusräume mit diesem Forecast-Link.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel/Beschreibung/Inhalt.'],
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

            if (! empty($arguments['id'])) {
                $fa = ManagementBookFocusArea::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $fa) {
                    return ToolResult::error('NOT_FOUND', 'Fokusraum nicht gefunden.');
                }
                if ($denied = $this->authorizeModel($context, 'view', $fa, 'Fokusraum')) {
                    return $denied;
                }
                $fa->load('visionImages', 'obstacles', 'milestones');
                $data = $this->focusAreaToArray($fa);
                $data['vision_images'] = $fa->visionImages->map(fn ($m) => $this->itemToArray($m))->all();
                $data['obstacles'] = $fa->obstacles->map(fn ($m) => $this->itemToArray($m))->all();
                $data['milestones'] = $fa->milestones->map(fn ($m) => $this->itemToArray($m))->all();

                return ToolResult::success(['focus_area' => $data]);
            }

            $query = ManagementBookFocusArea::query()->where('team_id', $team->id)
                ->whereIn('management_book_id', $this->visibleBookIds($team, $context));
            if (! empty($arguments['management_book_id'])) {
                $query->where('management_book_id', (int) $arguments['management_book_id']);
            }
            if (! empty($arguments['forecast_id'])) {
                $query->where('forecast_id', (int) $arguments['forecast_id']);
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term)->orWhere('content', 'like', $term));
            }

            $total = (clone $query)->count();
            [$limit, $offset] = $this->pageArgs($arguments);
            $rows = $query->orderBy('management_book_id')->orderBy('order')->orderBy('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'focus_areas' => $rows->map(fn ($fa) => $this->focusAreaToArray($fa, false))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Fokusräume: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('focus_areas', 'GET');
    }
}
