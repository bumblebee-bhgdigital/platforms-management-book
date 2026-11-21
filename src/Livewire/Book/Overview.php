<?php

namespace Platform\ManagementBook\Livewire\Book;

use Livewire\Component;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;
use Platform\ManagementBook\Services\BookInsights;

/**
 * Cockpit eines Books: Regelkreis (acht Stationen mit abgeleitetem Zustand), Kennzahlen,
 * Mission/Vision, Risikoprofil, nächste Sitzung und das, was gerade klemmt.
 */
class Overview extends Component
{
    use LoadsBook;

    /** Ausgewählte Regelkreis-Station (Index 0–7). */
    public int $station = 0;

    public function selectStation(int $index): void
    {
        $this->station = max(0, min(7, $index));
    }

    public function render()
    {
        $book = $this->book();
        $insights = app(BookInsights::class)->forBook($book);

        return view('management-book::livewire.book.overview', [
            'book' => $book,
            'i' => $insights,
            'states' => BookInsights::STATES,
        ]);
    }
}
