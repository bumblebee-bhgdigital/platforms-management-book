<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class ListMeetingAttendeesTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.meeting_attendees.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/meeting_attendees - Listet Teilnehmer von Sitzungen (nur sichtbare). Einzelner Eintrag: id. Filter: meeting_id, user_id, agent_id, attended, search (Rolle), limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->childListSchema('attendee');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildList('attendee', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('meeting_attendees', 'GET');
        $meta['tags'] = ['management-book', 'meetings', 'meeting_attendees', 'get'];

        return $meta;
    }
}
