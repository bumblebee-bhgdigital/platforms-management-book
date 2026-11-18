<?php

namespace Platform\ManagementBook\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationEntity;

/**
 * Verknüpfen / Umhängen / Lösen eines Books am Knoten aus der UI (Sidebar, Detailseite).
 *
 * Nutzt die Dimension Bridge über ManagementBookService (analog ProjectsCleanup::replaceLinks im Planner),
 * prüft Schreibrecht auf das Book (Policy) UND auf den Zielknoten (Core-Graph, writableEntityIds) und
 * dispatcht anschließend das UI-Event `updateSidebar`, auf das die Sidebar hört.
 * Der Service löst dabei ggf. das bisher aktive Book des Zielknotens ab.
 */
trait LinksBooksToNodes
{
    public ?int $linkingBookId = null;
    public string $linkSearch = '';

    public function startLinking(int $bookId): void
    {
        Gate::authorize('update', ManagementBook::findOrFail($bookId));
        $this->linkingBookId = $bookId;
        $this->linkSearch = '';
    }

    public function cancelLinking(): void
    {
        $this->linkingBookId = null;
        $this->linkSearch = '';
    }

    /** Verknüpfen (Book ohne Knoten) bzw. Umhängen (Book mit Knoten) auf $entityId. */
    public function linkTo(int $entityId): void
    {
        $book = ManagementBook::findOrFail((int) $this->linkingBookId);
        Gate::authorize('update', $book);

        if (! in_array($entityId, (new DimensionLinkAuthorizer())->writableEntityIds(auth()->user()), true)) {
            abort(403, 'Kein Schreibrecht auf diesen Knoten.');
        }

        $service = app(ManagementBookService::class);
        $service->nodeIds($book) === [] ? $service->link($book, $entityId) : $service->relink($book, $entityId);

        $this->afterLinkChange();
    }

    public function unlinkBook(int $bookId): void
    {
        $book = ManagementBook::findOrFail($bookId);
        Gate::authorize('update', $book);

        app(ManagementBookService::class)->unlink($book);

        $this->afterLinkChange();
    }

    protected function afterLinkChange(): void
    {
        $this->cancelLinking();
        $this->dispatch('updateSidebar');
    }

    /**
     * Kandidaten für den Knoten-Picker: Entities, auf die der User schreiben darf, optional gefiltert.
     *
     * @return array<int, array{id:int,name:string,type:?string}>
     */
    protected function linkCandidates(): array
    {
        $user = auth()->user();
        if (! $user || ! $this->linkingBookId) {
            return [];
        }

        $writable = (new DimensionLinkAuthorizer())->writableEntityIds($user);
        if ($writable === []) {
            return [];
        }

        $search = trim($this->linkSearch);

        return OrganizationEntity::query()
            ->with('type:id,name')
            ->whereIn('id', $writable)
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit($search !== '' ? 20 : 10)
            ->get(['id', 'name', 'entity_type_id'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'name' => $e->name, 'type' => $e->type?->name])
            ->all();
    }
}
