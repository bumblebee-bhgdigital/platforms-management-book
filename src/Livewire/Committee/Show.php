<?php

namespace Platform\ManagementBook\Livewire\Committee;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\ManagementBook\Models\ManagementBookCommittee;

class Show extends Component
{
    public ManagementBookCommittee $committee;

    public function mount(ManagementBookCommittee $committee): void
    {
        Gate::authorize('view', $committee);
        $this->committee = $committee;
    }

    public function render()
    {
        return view('management-book::livewire.committee.show', ['committee' => $this->committee])
            ->layout('platform::layouts.app');
    }
}
