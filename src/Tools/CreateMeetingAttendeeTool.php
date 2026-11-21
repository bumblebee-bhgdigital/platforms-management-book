<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Tools\Concerns\HandlesMeetingChildren;

class CreateMeetingAttendeeTool implements ToolContract, ToolMetadataContract
{
    use HandlesMeetingChildren;

    public function getName(): string
    {
        return 'management-book.meeting_attendees.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/meeting_attendees - Fügt einer Sitzung einen Teilnehmer hinzu. Pflicht: meeting_id und genau eines von user_id/agent_id/organization_entity_id (Personen-Knoten, z. B. Gremiumsmitglied ohne Plattform-Konto). Optional: role (frei), attended (Default false; auch nachträglich/historisch setzbar). Keine automatische Übernahme – die Gremiumsmitglieder (management-book.committees.GET, Feld members) liefern je Person user_id/agent_id oder entity_id.';
    }

    public function getSchema(): array
    {
        return $this->childCreateSchema('attendee');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return $this->runChildCreate('attendee', $arguments, $context);
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('meeting_attendees', 'POST');
        $meta['tags'] = ['management-book', 'meetings', 'meeting_attendees', 'post'];

        return $meta;
    }
}
