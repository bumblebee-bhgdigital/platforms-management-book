<?php

namespace Platform\ManagementBook\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookDecision;

/**
 * Ein Gremium hat zu einem Agendapunkt beschlossen. Andere Module (z. B. Signals) hören darauf, wenn der
 * Agendapunkt mit einem ihrer Objekte verknüpft ist (linked_object_type/id). Das Book kennt die Abnehmer nicht.
 */
class AgendaItemDecided
{
    use Dispatchable;

    public function __construct(
        public readonly ManagementBookAgendaItem $agendaItem,
        public readonly ManagementBookDecision $decision,
    ) {
    }
}
