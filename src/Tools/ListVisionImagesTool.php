<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class ListVisionImagesTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.vision_images.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/vision_images - Listet Zielbilder (nur sichtbare Books), nach Fokusraum/Reihenfolge sortiert. Einzelnes Element: id=<id>. Filter: management_book_id, focus_area_id, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->itemListSchema('vision_image');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemList('vision_image', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('vision_images', 'GET');
    }
}
