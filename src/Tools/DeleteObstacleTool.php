<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class DeleteObstacleTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.obstacles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/obstacles/{id} - Löscht ein Element der Spalte „Hindernis“ (Soft-Delete, wiederherstellbar). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->itemDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemDelete('obstacle', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('obstacles', 'DELETE');
    }
}
