<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class CreateDocumentTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.documents.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/documents - Legt eine Unterlage zu einer Sitzung an. Pflicht: meeting_id, title. Optional: type (frei), agenda_item_id, context_file_ids (Dateien aus den Core-ContextFiles des Teams, Verknüpfung über context_file_references; kein eigener Dateispeicher).';
    }

    public function getSchema(): array
    {
        return $this->childCreateSchema('document');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildCreate('document', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('documents', 'POST');
        $meta['tags'] = ['management-book', 'meetings', 'documents', 'post'];

        return $meta;
    }
}
