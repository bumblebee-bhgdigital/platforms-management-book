<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class DeleteAgendaItemTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.agenda_items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/agenda_items/{id} - Löscht einen Agenda-Punkt (Soft-Delete). Zugeordnete Unterlagen/Beschlüsse bleiben erhalten (agenda_item_id wird beim endgültigen Löschen genullt). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildDelete('agenda_item', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('agenda_items', 'DELETE');
        $meta['tags'] = ['management-book', 'meetings', 'agenda_items', 'delete'];

        return $meta;
    }
}
