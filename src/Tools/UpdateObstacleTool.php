<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class UpdateObstacleTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.obstacles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/obstacles/{id} - Ändert ein Element der Spalte „Hindernis“ (auch in einen anderen Fokusraum desselben Books verschieben). Pflicht: id. Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return $this->itemUpdateSchema('obstacle');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemUpdate('obstacle', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('obstacles', 'PUT');
    }
}
