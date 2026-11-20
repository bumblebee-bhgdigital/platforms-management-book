<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class ListObstaclesTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.obstacles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/obstacles - Listet Hindernisse (nur sichtbare Books), nach Fokusraum/Reihenfolge sortiert. Einzelnes Element: id=<id>. Filter: management_book_id, focus_area_id, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->itemListSchema('obstacle');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemList('obstacle', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('obstacles', 'GET');
    }
}
