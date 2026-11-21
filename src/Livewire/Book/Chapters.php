<?php

namespace Platform\ManagementBook\Livewire\Book;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;

/**
 * Das Book als Dokument: Kapitel (Mission, Vision, Herkunft, Standort, …) in Lesereihenfolge.
 * Jede Änderung legt eine neue aktive Version an (wie management-book.strategy.PUT), die alte bleibt Historie.
 */
class Chapters extends Component
{
    use LoadsBook;

    public bool $showEdit = false;
    public ?string $editType = null;
    public array $form = ['title' => '', 'content' => '', 'change_note' => ''];

    public bool $showHistory = false;
    public ?string $historyType = null;

    public function edit(string $type): void
    {
        $this->authorizeEdit();
        $t = StrategicDocumentType::from($type);
        $doc = ManagementBookStrategicDocument::activeFor($this->bookId, $t);
        $this->editType = $t->value;
        $this->form = ['title' => $doc?->title ?? $t->label(), 'content' => (string) ($doc?->content ?? ''), 'change_note' => ''];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function save(): void
    {
        $book = $this->authorizeEdit();
        $t = StrategicDocumentType::from((string) $this->editType);
        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.content' => 'nullable|string',
            'form.change_note' => 'nullable|string|max:500',
        ]);

        $current = ManagementBookStrategicDocument::activeFor($book->id, $t);
        $title = trim($this->form['title']);
        $content = (string) $this->form['content'];

        // Unveränderter Inhalt erzeugt keine neue Version (wie strategy.PUT)
        if (! $current || $current->content !== $content || $current->title !== $title) {
            ManagementBookStrategicDocument::create([
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'type' => $t,
                'title' => $title,
                'content' => $content,
                'is_active' => true,
                'valid_from' => now()->toDateString(),
                'change_note' => trim((string) $this->form['change_note']) ?: null,
                'created_by' => Auth::id(),
            ]);
        }

        $this->showEdit = false;
    }

    public function history(string $type): void
    {
        $this->historyType = StrategicDocumentType::from($type)->value;
        $this->showHistory = true;
    }

    /** Was in ein Kapitel gehört (Hinweis für leere Kapitel und im Editor). */
    public const HINTS = [
        'origin' => 'Woher kommen wir? Gründung, prägende Entscheidungen und Wendepunkte, auf die wir stolz sind – und was wir daraus gelernt haben.',
        'current_situation' => 'Wo stehen wir heute? Markt, Kunden, Zahlen, Stärken und offene Flanken – ehrlich, so wie es sich von innen und außen anfühlt.',
        'mission' => 'Warum gibt es uns? Ein, zwei Sätze, die auch in zehn Jahren noch stimmen: für wen wir da sind und welchen Unterschied wir machen.',
        'vision' => 'Wohin wollen wir? Ein Zukunftsbild, das man sehen kann – ein beobachtbarer Zustand in fünf bis zehn Jahren, keine Floskel wie „Marktführer werden“.',
        'priorities' => 'Welche Prioritäten gelten? Was hat Vorrang, wenn Ressourcen knapp werden – und was lassen wir bewusst weg?',
        'business_fields' => 'Welche Bedeutung hat welches Geschäftsfeld? Was trägt heute, was soll wachsen, was läuft aus – und warum?',
        'principles' => 'Nach welchen Grundsätzen führen und entscheiden wir? Zum Beispiel: Hilfe statt Macht, lokal vor übergreifend, Eskalation ist professionell.',
        'capabilities' => 'Welche Fähigkeiten müssen wir aufbauen? Was wir können müssen, um die Vision zu erreichen, und was uns heute noch fehlt.',
        'collaboration' => 'Was heißt gute Zusammenarbeit? Wie wir miteinander, mit Kunden und mit Partnern umgehen – konkret genug, um sich daran messen zu lassen.',
    ];

    public function render()
    {
        $book = $this->book();
        $docs = ManagementBookStrategicDocument::query()->where('management_book_id', $book->id)->active()->get()
            ->keyBy(fn ($d) => $d->type instanceof StrategicDocumentType ? $d->type->value : (string) $d->type);

        $chapters = collect(StrategicDocumentType::bookOrder())->map(fn (StrategicDocumentType $t) => [
            'type' => $t,
            'doc' => $docs->get($t->value),
        ]);

        $versions = collect();
        if ($this->showHistory && $this->historyType) {
            $versions = ManagementBookStrategicDocument::query()->where('management_book_id', $book->id)
                ->ofType($this->historyType)->orderByDesc('version')->get();
        }

        $authors = \Platform\Core\Models\User::query()
            ->whereIn('id', $docs->pluck('created_by')->merge($versions->pluck('created_by'))->filter()->unique())
            ->pluck('name', 'id');

        return view('management-book::livewire.book.chapters', [
            'authors' => $authors,
            'book' => $book,
            'chapters' => $chapters,
            'filled' => $chapters->filter(fn ($c) => filled($c['doc']?->content))->count(),
            'canEdit' => $this->canEdit(),
            'editLabel' => $this->editType ? StrategicDocumentType::from($this->editType) : null,
            'historyLabel' => $this->historyType ? StrategicDocumentType::from($this->historyType) : null,
            'versions' => $versions,
            'hints' => self::HINTS,
        ]);
    }
}
