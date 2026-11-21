<?php

namespace Platform\ManagementBook\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Platform\ManagementBook\Models\ManagementBook;

/**
 * Gemeinsame Basis der Bereichs-Komponenten einer Book-Detailseite (management-book.book.*):
 * Book per ID laden, Leserecht prüfen, Schreibrecht für die UI abfragen.
 */
trait LoadsBook
{
    public int $bookId;

    public function mount(int $bookId): void
    {
        $this->bookId = $bookId;
        Gate::authorize('view', $this->book());
    }

    protected function book(): ManagementBook
    {
        return ManagementBook::findOrFail($this->bookId);
    }

    protected function canEdit(): bool
    {
        return Gate::forUser(Auth::user())->allows('update', $this->book());
    }

    protected function authorizeEdit(): ManagementBook
    {
        $book = $this->book();
        Gate::authorize('update', $book);

        return $book;
    }
}
