<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class ListDecisionsTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.decisions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/decisions - Listet Beschlüsse von Sitzungen (nur sichtbare), nach Fälligkeit sortiert. Einzelner Beschluss: id. Filter: meeting_id, agenda_item_id, status (open/implemented/discarded), owner_user_id/owner_agent_id, due_before, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->childListSchema('decision');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildList('decision', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('decisions', 'GET');
        $meta['tags'] = ['management-book', 'meetings', 'decisions', 'get'];

        return $meta;
    }
}
