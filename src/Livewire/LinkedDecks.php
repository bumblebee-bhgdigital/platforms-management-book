<?php

namespace Platform\ManagementBook\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * Präsentationen (Slides-Decks), die lose an einer Sitzung oder einem Gremium hängen.
 *
 * Lose Kopplung: nur aktiv, wenn das Slides-Modul installiert ist (SlidesDeck, SlidesDeckLink, DeckService).
 * Verknüpft wird über slides_deck_links (linkable_type = Morph-Alias des Management-Book-Objekts).
 * Agenten bereiten Decks über die slides.*-Tools vor; die Vorschau aktualisiert sich über die Deck-Revision.
 */
class LinkedDecks extends Component
{
    public string $linkableType;

    public int $linkableId;

    /** Kompakte Darstellung (Gremium-Seite). */
    public bool $compact = false;

    public bool $showCreate = false;

    public string $newTitle = '';

    public bool $showLink = false;

    public string $linkSearch = '';

    public static function available(): bool
    {
        return class_exists(\Platform\Slides\Models\SlidesDeck::class)
            && class_exists(\Platform\Slides\Models\SlidesDeckLink::class)
            && class_exists(\Platform\Slides\Services\DeckService::class);
    }

    public function mount(string $linkableType, int $linkableId, bool $compact = false): void
    {
        $this->linkableType = $linkableType;
        $this->linkableId = $linkableId;
        $this->compact = $compact;
        Gate::authorize('view', $this->parent());
    }

    protected function parent(): Model
    {
        $class = Relation::getMorphedModel($this->linkableType);
        abort_unless($class && is_subclass_of($class, Model::class), 404);

        return $class::findOrFail($this->linkableId);
    }

    protected function canEdit(): bool
    {
        return Gate::forUser(Auth::user())->allows('update', $this->parent());
    }

    public function openCreate(): void
    {
        abort_unless(self::available() && $this->canEdit(), 403);
        $parent = $this->parent();
        $this->newTitle = $parent->title ?? $parent->name ?? 'Präsentation';
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function createDeck(): void
    {
        abort_unless(self::available() && $this->canEdit(), 403);
        $this->validate(['newTitle' => 'required|string|max:255']);
        $parent = $this->parent();

        $service = app(\Platform\Slides\Services\DeckService::class);
        $deck = $service->createDeck(Auth::user(), (int) $parent->team_id, ['title' => trim($this->newTitle)])['deck'];
        $service->link($deck, $this->linkableType, $this->linkableId, 'presentation', Auth::user());

        $this->showCreate = false;
        $this->newTitle = '';
    }

    public function openLink(): void
    {
        abort_unless(self::available() && $this->canEdit(), 403);
        $this->linkSearch = '';
        $this->showLink = true;
    }

    public function linkDeck(int $deckId): void
    {
        abort_unless(self::available() && $this->canEdit(), 403);
        $deck = \Platform\Slides\Models\SlidesDeck::findOrFail($deckId);
        Gate::authorize('view', $deck);
        app(\Platform\Slides\Services\DeckService::class)->link($deck, $this->linkableType, $this->linkableId, 'presentation', Auth::user());
        $this->showLink = false;
    }

    public function unlinkDeck(int $deckId): void
    {
        abort_unless(self::available() && $this->canEdit(), 403);
        $deck = \Platform\Slides\Models\SlidesDeck::findOrFail($deckId);
        app(\Platform\Slides\Services\DeckService::class)->unlink($deck, $this->linkableType, $this->linkableId);
    }

    public function render()
    {
        if (! self::available()) {
            return view('management-book::livewire.linked-decks', ['available' => false, 'decks' => collect(), 'candidates' => collect(), 'canEdit' => false, 'routes' => [], 'revisionKey' => '']);
        }

        $user = Auth::user();
        $deckIds = \Platform\Slides\Models\SlidesDeckLink::query()
            ->where('linkable_type', $this->linkableType)->where('linkable_id', $this->linkableId)
            ->pluck('deck_id');

        $decks = \Platform\Slides\Models\SlidesDeck::query()
            ->whereIn('id', $deckIds)
            ->withCount('slides')
            ->with(['slides' => fn ($q) => $q->where('is_hidden', false)->orderBy('position')->limit(1)])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn ($d) => Gate::forUser($user)->allows('view', $d))
            ->values();

        $candidates = collect();
        if ($this->showLink) {
            $parent = $this->parent();
            $candidates = \Platform\Slides\Models\SlidesDeck::query()
                ->where('team_id', $parent->team_id)
                ->whereNotIn('id', $deckIds)
                ->when(trim($this->linkSearch) !== '', fn ($q) => $q->where('title', 'like', '%' . trim($this->linkSearch) . '%'))
                ->orderByDesc('updated_at')->limit(10)->get()
                ->filter(fn ($d) => Gate::forUser($user)->allows('view', $d))
                ->values();
        }

        return view('management-book::livewire.linked-decks', [
            'available' => true,
            'decks' => $decks,
            'candidates' => $candidates,
            'canEdit' => $this->canEdit(),
            'routes' => [
                'show' => Route::has('slides.decks.show'),
                'present' => Route::has('slides.decks.present'),
                'presenter' => Route::has('slides.decks.presenter'),
                'pdf' => Route::has('slides.decks.pdf'),
            ],
            // Live-Vorschau: ändert ein Agent ein Deck, steigt dessen revision → neu rendern
            'revisionKey' => $decks->map(fn ($d) => $d->id . ':' . $d->revision)->implode(','),
        ]);
    }
}
