<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class UpdateMeetingAttendeeTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.meeting_attendees.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/meeting_attendees/{id} - Ändert einen Teilnehmer (role, attended, user_id/agent_id/organization_entity_id). Pflicht: id.';
    }

    public function getSchema(): array
    {
        return $this->childUpdateSchema('attendee');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildUpdate('attendee', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('meeting_attendees', 'PUT');
        $meta['tags'] = ['management-book', 'meetings', 'meeting_attendees', 'put'];

        return $meta;
    }
}
