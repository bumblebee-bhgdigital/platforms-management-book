<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesFocusAreaItems;

class UpdateVisionImageTool implements ToolContract, ToolMetadataContract
{
    use HandlesFocusAreaItems;

    public function getName(): string
    {
        return 'management-book.vision_images.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/vision_images/{id} - Ändert ein Element der Spalte „Zielbild“ (auch in einen anderen Fokusraum desselben Books verschieben). Pflicht: id. Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return $this->itemUpdateSchema('vision_image');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runItemUpdate('vision_image', $arguments, $context);
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('vision_images', 'PUT');
    }
}
