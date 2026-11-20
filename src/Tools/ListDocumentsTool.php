<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class ListDocumentsTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.documents.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/documents - Listet Unterlagen von Sitzungen (nur sichtbare) inkl. verknüpfter Core-ContextFiles (files). Einzelne Unterlage: id. Filter: meeting_id, agenda_item_id, search, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->childListSchema('document');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildList('document', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('documents', 'GET');
        $meta['tags'] = ['management-book', 'meetings', 'documents', 'get'];

        return $meta;
    }
}
