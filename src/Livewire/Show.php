<?php

/**
 * Detailseite eines Management Books: Stammdaten, Knoten-Verknüpfung (verknüpfen/umhängen/lösen),
 * Aktivieren/Archivieren und leere Platzhalter-Bereiche für Strategie, Risiken, Analysen und Gremien
 * (folgen in eigenen Boards).
 */

namespace Platform\ManagementBook\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Enums\ReviewRhythm;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Livewire\Concerns\LinksBooksToNodes;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Models\OrganizationEntity;

class Show extends Component
{
    use HasPageContext, LinksBooksToNodes;

    public ManagementBook $managementBook;

    /** Aktiver Bereich: overview | book | strategy | risks | committees */
    #[Url(as: 'tab')]
    public string $tab = 'overview';

    public const TABS = [
        'overview' => ['label' => 'Übersicht', 'icon' => 'heroicon-o-squares-2x2'],
        'book' => ['label' => 'Book', 'icon' => 'heroicon-o-book-open'],
        'strategy' => ['label' => 'Strategie', 'icon' => 'heroicon-o-flag'],
        'risks' => ['label' => 'Risiken', 'icon' => 'heroicon-o-exclamation-triangle'],
        'committees' => ['label' => 'Gremien', 'icon' => 'heroicon-o-user-group'],
    ];

    public bool $showEdit = false;
    public array $form = [];

    public function mount(ManagementBook $managementBook): void
    {
        Gate::authorize('view', $managementBook);
        $this->managementBook = $managementBook;
    }

    /**
     * Seitenkontext (Terminal/Kontext-Panel). Das Book hängt per HasEntityLinks an Knoten —
     * die App "Verknüpfungen" im Kontext-Panel ist damit automatisch verfügbar und nutzt dieselbe Bridge.
     */
    protected function pageContext(): ?Model
    {
        return $this->managementBook;
    }

    protected function pageContextOptions(): array
    {
        return [
            'subject' => $this->managementBook->title,
            'url' => route('management-book.books.show', $this->managementBook),
            'source' => 'management-book.book.view',
            'capabilities' => ['manage_channels' => true, 'threads' => false],
            'meta' => [
                'status' => $this->managementBook->status?->value,
                'created_at' => $this->managementBook->created_at,
            ],
        ];
    }

    #[On('updateSidebar')]
    public function refreshBook(): void
    {
        $this->managementBook->refresh();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'overview';
    }

    public function openEdit(): void
    {
        Gate::authorize('update', $this->managementBook);
        $b = $this->managementBook;
        $this->form = [
            'title' => $b->title,
            'description' => $b->description,
            'horizon_year' => $b->horizon_year,
            'valid_from' => $b->valid_from?->toDateString(),
            'valid_until' => $b->valid_until?->toDateString(),
            'review_rhythm' => $b->review_rhythm?->value,
            'next_review_at' => $b->next_review_at?->toDateString(),
        ];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->managementBook);

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
            $this->managementBook = app(ManagementBookService::class)->update($this->managementBook, $this->form);
        } catch (ManagementBookException $e) {
            $this->addError('form.title', $e->getMessage());

            return;
        }

        $this->showEdit = false;
        $this->dispatch('updateSidebar');
    }

    public function activate(): void
    {
        Gate::authorize('activate', $this->managementBook);

        try {
            $result = app(ManagementBookService::class)->activate($this->managementBook);
        } catch (ManagementBookException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->managementBook = $result['book'];
        $this->dispatch('updateSidebar');
    }

    public function archive(): void
    {
        Gate::authorize('update', $this->managementBook);

        $this->managementBook = app(ManagementBookService::class)->archive($this->managementBook);
        $this->dispatch('updateSidebar');
    }

    public function deleteBook()
    {
        Gate::authorize('delete', $this->managementBook);

        app(ManagementBookService::class)->delete($this->managementBook);
        $this->dispatch('updateSidebar');

        return $this->redirect(route('management-book.dashboard'), navigate: true);
    }

    protected function afterLinkChange(): void
    {
        $this->cancelLinking();
        $this->managementBook->refresh();
        $this->dispatch('updateSidebar');
    }

    public function render()
    {
        $service = app(ManagementBookService::class);
        $book = $this->managementBook;

        $nodeIds = $service->nodeIds($book);
        $nodes = $nodeIds === [] ? collect() : OrganizationEntity::with('type:id,name')->whereIn('id', $nodeIds)->get();

        // Welche aktiven Books würden beim Aktivieren abgelöst?
        $wouldSupersede = collect();
        if ($book->status !== ManagementBookStatus::ACTIVE && $book->status !== ManagementBookStatus::ARCHIVED) {
            foreach ($nodeIds as $entityId) {
                $active = $service->findActiveForNode($entityId);
                if ($active && $active->id !== $book->id) {
                    $wouldSupersede->put($active->id, $active);
                }
            }
        }

        return view('management-book::livewire.show', [
            'book' => $book,
            'nodes' => $nodes,
            'wouldSupersede' => $wouldSupersede->values(),
            'tabs' => self::TABS,
            'activeTab' => array_key_exists($this->tab, self::TABS) ? $this->tab : 'overview',
            'linkCandidates' => $this->linkCandidates(),
            'firstSteps' => app(\Platform\ManagementBook\Services\BookInsights::class)->firstSteps($book),
            'canEdit' => Gate::allows('update', $book),
            'rhythmOptions' => array_map(fn (ReviewRhythm $r) => ['value' => $r->value, 'label' => $r->label()], ReviewRhythm::cases()),
        ])->layout('platform::layouts.app');
    }
}
