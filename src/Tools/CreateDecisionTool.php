<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class CreateDecisionTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.decisions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/decisions - Legt einen Beschluss an einer Sitzung an. Pflicht: meeting_id, text. Optional: status (Default open), agenda_item_id, owner_user_id|owner_agent_id, due_date, linked_task_id (lose Planner-Verknüpfung, keine automatische Aufgabenanlage).';
    }

    public function getSchema(): array
    {
        return $this->childCreateSchema('decision');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildCreate('decision', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('decisions', 'POST');
        $meta['tags'] = ['management-book', 'meetings', 'decisions', 'post'];

        return $meta;
    }
}
