<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class UpdateAgendaItemTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.agenda_items.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/agenda_items/{id} - Ändert einen Agenda-Punkt (alle Felder frei, auch Reihenfolge per position). Pflicht: id. Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return $this->childUpdateSchema('agenda_item');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildUpdate('agenda_item', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('agenda_items', 'PUT');
        $meta['tags'] = ['management-book', 'meetings', 'agenda_items', 'put'];

        return $meta;
    }
}
