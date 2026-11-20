<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class UpdateDecisionTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.decisions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/decisions/{id} - Ändert einen Beschluss (z. B. status=implemented/discarded, Verantwortlicher, Fälligkeit). Pflicht: id. Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return $this->childUpdateSchema('decision');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildUpdate('decision', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('decisions', 'PUT');
        $meta['tags'] = ['management-book', 'meetings', 'decisions', 'put'];

        return $meta;
    }
}
