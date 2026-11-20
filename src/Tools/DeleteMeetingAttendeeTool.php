<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class DeleteMeetingAttendeeTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.meeting_attendees.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/meeting_attendees/{id} - Entfernt einen Teilnehmer aus der Sitzung (hart gelöscht, kein Soft-Delete). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childDeleteSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildDelete('attendee', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('meeting_attendees', 'DELETE');
        $meta['tags'] = ['management-book', 'meetings', 'meeting_attendees', 'delete'];

        return $meta;
    }
}
