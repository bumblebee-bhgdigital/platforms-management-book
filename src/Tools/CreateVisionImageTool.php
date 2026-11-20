<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class CreateVisionImageTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.vision_images.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/vision_images - Legt ein Element der Spalte „Zielbild“ in einem Fokusraum an. Pflicht: focus_area_id, title. Optional: description, central_question, order.';
    }

    public function getSchema(): array
    {
        return $this->itemCreateSchema('vision_image');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemCreate('vision_image', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('vision_images', 'POST');
    }
}
