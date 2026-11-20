<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class DeleteMilestoneTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.milestones.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/milestones/{id} - Löscht ein Element der Spalte „Meilenstein“ (Soft-Delete, wiederherstellbar). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->itemDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemDelete('milestone', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestones', 'DELETE');
    }
}
