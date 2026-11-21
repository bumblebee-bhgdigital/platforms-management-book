<?php

namespace Platform\ManagementBook\Livewire\Book;

use Livewire\Component;
use Platform\ManagementBook\Livewire\Concerns\LoadsBook;

class Strategy extends Component
{
    use LoadsBook;

    public function render()
    {
        return view('management-book::livewire.book.strategy', ['book' => $this->book()]);
    }
}
