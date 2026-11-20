<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class UpdateMilestoneTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.milestones.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/milestones/{id} - Ändert ein Element der Spalte „Meilenstein“ (auch in einen anderen Fokusraum desselben Books verschieben). Pflicht: id. Nicht übergebene Felder bleiben unverändert. Meilensteine sind Waypoints der Transformation Map: target_year + target_quarter (1-4), optional target_date; status (planned|in_progress|reached|missed|cancelled, Default planned). Gesamtbild über die Zeit: management-book.transformation_map.GET.';
    }

    public function getSchema(): array
    {
        return $this->itemUpdateSchema('milestone');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemUpdate('milestone', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestones', 'PUT');
    }
}
