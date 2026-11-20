<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class UpdateDocumentTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.documents.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/documents/{id} - Ändert eine Unterlage. Pflicht: id. Dateien: context_file_ids (hinzufügen), remove_context_file_ids (Verknüpfung entfernen, Datei bleibt). Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return $this->childUpdateSchema('document');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildUpdate('document', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('documents', 'PUT');
        $meta['tags'] = ['management-book', 'meetings', 'documents', 'put'];

        return $meta;
    }
}
