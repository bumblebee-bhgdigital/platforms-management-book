<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class DeleteMinutesTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.minutes.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/minutes/{id} - Löscht ein Protokoll (Soft-Delete). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildDelete('minutes', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('minutes', 'DELETE');
        $meta['tags'] = ['management-book', 'meetings', 'minutes', 'delete'];

        return $meta;
    }
}
