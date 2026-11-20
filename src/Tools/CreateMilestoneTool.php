<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class CreateMilestoneTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.milestones.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/milestones - Legt ein Element der Spalte „Meilenstein“ in einem Fokusraum an. Pflicht: focus_area_id, title. Optional: description, central_question, order. Meilensteine sind Waypoints der Transformation Map: target_year + target_quarter (1-4), optional target_date.';
    }

    public function getSchema(): array
    {
        return $this->itemCreateSchema('milestone');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemCreate('milestone', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestones', 'POST');
    }
}
