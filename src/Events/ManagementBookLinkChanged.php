<?php

namespace Platform\ManagementBook\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Platform\ManagementBook\Models\ManagementBook;

/**
 * Wird dispatched, wenn die Knoten-Verknüpfung (DimensionLink) eines Books sich ändert
 * (verknüpft / gelöst / umgehängt) oder ein Book über die Aktivierungs-Regel abgelöst wird.
 *
 * Dient der Sidebar-Verknüpfung: Livewire-Komponenten hören auf das UI-Event `updateSidebar`
 * (siehe Sidebar::updateSidebar) — dieses Laravel-Event ist die serverseitige Entsprechung
 * für Listener anderer Module (z. B. Cache-Invalidierung, Signale).
 */
class ManagementBookLinkChanged
{
    use Dispatchable;

    public const LINKED = 'linked';
    public const UNLINKED = 'unlinked';
    public const RELINKED = 'relinked';
    public const SUPERSEDED = 'superseded';

    public function __construct(
        public readonly ManagementBook $book,
        public readonly string $action,
        public readonly ?int $entityId = null,
        public readonly ?int $previousEntityId = null,
    ) {
    }
}
