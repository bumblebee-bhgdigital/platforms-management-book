<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class UpdateMinutesTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.minutes.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/minutes/{id} - Ändert ein Protokoll (content, approved_at, approved_by frei schreibbar; null setzt zurück). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childUpdateSchema('minutes');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildUpdate('minutes', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('minutes', 'PUT');
        $meta['tags'] = ['management-book', 'meetings', 'minutes', 'put'];

        return $meta;
    }
}
