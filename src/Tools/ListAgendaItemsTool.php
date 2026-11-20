<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class ListAgendaItemsTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.agenda_items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/agenda_items - Listet Agenda-Punkte von Sitzungen (nur sichtbare), nach Position sortiert. Einzelner Punkt: id. Filter: meeting_id, owner_user_id/owner_agent_id, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->childListSchema('agenda_item');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildList('agenda_item', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('agenda_items', 'GET');
        $meta['tags'] = ['management-book', 'meetings', 'agenda_items', 'get'];

        return $meta;
    }
}
