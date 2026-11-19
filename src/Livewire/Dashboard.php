<?php

/**
 * Übersichtsseite: alle Management Books des Teams (Team-Familie, nur sichtbare), mit Statusfilter,
 * Knoten-Anzeige und Anlegen. Route-Name bleibt management-book.dashboard (Modul-Navigation).
 */

namespace Platform\ManagementBook\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\ReviewRhythm;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;

class Dashboard extends Component
{
    use ResolvesTeamFamily;

    /** '' = alle Status */
    public string $statusFilter = '';

    public bool $showCreate = false;
    public array $form = [
        'title' => '',
        'description' => '',
        'horizon_year' => null,
        'valid_from' => null,
        'valid_until' => null,
        'review_rhythm' => null,
        'next_review_at' => null,
    ];

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Management Books',
            'description' => 'Übersicht aller Management Books',
            'url' => route('management-book.dashboard'),
            'source' => 'management-book.dashboard',
            'recipients' => [],
            'meta' => ['view_type' => 'dashboard'],
        ]);
    }

    #[On('updateSidebar')]
    public function refreshList(): void
    {
    }

    public function openCreate(): void
    {
        Gate::authorize('create', ManagementBook::class);
        $this->reset('form');
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create()
    {
        Gate::authorize('create', ManagementBook::class);

        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.horizon_year' => 'nullable|integer|min:1900|max:2200',
            'form.valid_from' => 'nullable|date',
            'form.valid_until' => 'nullable|date',
            'form.review_rhythm' => 'nullable|in:' . implode(',', ReviewRhythm::values()),
            'form.next_review_at' => 'nullable|date',
        ]);

        try {
            $book = app(ManagementBookService::class)->create(Auth::user(), Auth::user()->currentTeam, $this->form);
        } catch (ManagementBookException $e) {
            $this->addError('form.title', $e->getMessage());

            return null;
        }

        $this->showCreate = false;
        $this->dispatch('updateSidebar');

        return $this->redirect(route('management-book.books.show', $book), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $teamIds = $this->relevantTeamIds($user);

        $all = ManagementBook::query()
            ->with(['owner:id,name', 'team:id,name'])
            ->whereIn('team_id', $teamIds)
            ->visibleTo($user)
            ->orderBy('title')
            ->get();

        // Knoten je Book (eine Abfrage)
        $nodesByBook = [];
        $links = EntityDimensionBridge::linksForLinkables([ManagementBook::MORPH_ALIAS], $all->pluck('id')->all(), false);
        $entityNames = OrganizationEntity::whereIn('id', $links->pluck('entity_id')->filter()->unique()->all())->pluck('name', 'id');
        foreach ($links as $link) {
            if ($link->entity_id) {
                $nodesByBook[$link->linkable_id][] = ['id' => (int) $link->entity_id, 'name' => $entityNames[$link->entity_id] ?? '—'];
            }
        }

        $books = $this->statusFilter !== ''
            ? $all->filter(fn ($b) => $b->status->value === $this->statusFilter)->values()
            : $all;

        $stats = [
            'total' => $all->count(),
            'active' => $all->where('status', ManagementBookStatus::ACTIVE)->count(),
            'draft' => $all->where('status', ManagementBookStatus::DRAFT)->count(),
            'review_due' => $all->filter(fn ($b) => $b->next_review_at && $b->next_review_at->lte(now()->endOfDay())
                && in_array($b->status, [ManagementBookStatus::ACTIVE, ManagementBookStatus::DRAFT], true))->count(),
        ];

        return view('management-book::livewire.dashboard', [
            'books' => $books,
            'nodesByBook' => $nodesByBook,
            'stats' => $stats,
            'multiTeam' => count($teamIds) > 1,
            'statusOptions' => array_map(fn (ManagementBookStatus $s) => ['value' => $s->value, 'label' => $s->label()], ManagementBookStatus::cases()),
            'rhythmOptions' => array_map(fn (ReviewRhythm $r) => ['value' => $r->value, 'label' => $r->label()], ReviewRhythm::cases()),
        ])->layout('platform::layouts.app');
    }
}
