<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class CreateAgendaItemTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.agenda_items.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/agenda_items - Legt einen Agenda-Punkt an einer Sitzung an. Pflicht: meeting_id, title. Optional: position, description, owner_user_id|owner_agent_id, duration_minutes, linked_object_type+linked_object_id. Es gibt keine Standard-Agenda – nur was der Agent anlegt. Das Format des Gremientyps bestimmt kind, die genutzten Leitfaden-Spalten und outcome_checks (LA: vier Fragen + Handlungsfähigkeit; Strategiemeeting: Ausgangslage/Richtungsfrage; Board: Lage/Vorlage/Abweichung) – Details: management-book.committees.GET id=<committee_id> → format.';
    }

    public function getSchema(): array
    {
        return $this->childCreateSchema('agenda_item');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildCreate('agenda_item', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('agenda_items', 'POST');
        $meta['tags'] = ['management-book', 'meetings', 'agenda_items', 'post'];

        return $meta;
    }
}
