<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class DeleteDocumentTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.documents.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/documents/{id} - Löscht eine Unterlage (Soft-Delete). Verknüpfte Dateien bleiben in den Core-ContextFiles erhalten. Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildDelete('document', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('documents', 'DELETE');
        $meta['tags'] = ['management-book', 'meetings', 'documents', 'delete'];

        return $meta;
    }
}
