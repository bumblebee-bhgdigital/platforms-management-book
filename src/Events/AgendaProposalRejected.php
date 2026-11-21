<?php

namespace Platform\ManagementBook\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;

/**
 * Der Vorsitz hat einen vorgeschlagenen Agendapunkt (proposal_status = draft) herausgenommen.
 * Abnehmer prüfen selbst, ob das verknüpfte Objekt ihres ist (linked_object_type/id).
 */
class AgendaProposalRejected
{
    use Dispatchable;

    public function __construct(
        public readonly ManagementBookAgendaItem $agendaItem,
        public readonly ?int $byUserId = null,
    ) {
    }
}
