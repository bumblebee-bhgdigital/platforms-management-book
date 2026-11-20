<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class CreateMinutesTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.minutes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/minutes - Legt ein Protokoll (Markdown) zu einer Sitzung an. Pflicht: meeting_id. Optional: content, approved_at, approved_by (frei setzbar, auch rückwirkend für historische Sitzungen; keine Freigabe-Automatik).';
    }

    public function getSchema(): array
    {
        return $this->childCreateSchema('minutes');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildCreate('minutes', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('minutes', 'POST');
        $meta['tags'] = ['management-book', 'meetings', 'minutes', 'post'];

        return $meta;
    }
}
