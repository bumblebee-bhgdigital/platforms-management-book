<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class DeleteDecisionTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.decisions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/decisions/{id} - Löscht einen Beschluss (Soft-Delete). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildDelete('decision', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('decisions', 'DELETE');
        $meta['tags'] = ['management-book', 'meetings', 'decisions', 'delete'];

        return $meta;
    }
}
