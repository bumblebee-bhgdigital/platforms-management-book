<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class ListMinutesTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.minutes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/minutes - Listet Protokolle von Sitzungen (nur sichtbare). Einzelnes Protokoll: id. Filter: meeting_id, approved (true/false), search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->childListSchema('minutes');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildList('minutes', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('minutes', 'GET');
        $meta['tags'] = ['management-book', 'meetings', 'minutes', 'get'];

        return $meta;
    }
}
