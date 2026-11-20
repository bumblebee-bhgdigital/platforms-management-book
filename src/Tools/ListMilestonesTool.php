<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class ListMilestonesTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.milestones.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/milestones - Listet Meilensteine (nur sichtbare Books), nach Fokusraum/Reihenfolge sortiert. Einzelnes Element: id=<id>. Filter: management_book_id, focus_area_id, target_year, target_quarter, status, search, limit/offset. Meilensteine sind Waypoints der Transformation Map: target_year + target_quarter (1-4), optional target_date; status (planned|in_progress|reached|missed|cancelled, Default planned). Gesamtbild über die Zeit: management-book.transformation_map.GET.';
    }

    public function getSchema(): array
    {
        return $this->itemListSchema('milestone');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemList('milestone', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestones', 'GET');
    }
}
