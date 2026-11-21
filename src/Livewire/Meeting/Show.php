<?php

namespace Platform\ManagementBook\Livewire\Meeting;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\ManagementBook\Models\ManagementBookMeeting;

class Show extends Component
{
    public ManagementBookMeeting $meeting;

    public function mount(ManagementBookMeeting $meeting): void
    {
        Gate::authorize('view', $meeting);
        $this->meeting = $meeting;
    }

    public function render()
    {
        return view('management-book::livewire.meeting.show', ['meeting' => $this->meeting])
            ->layout('platform::layouts.app');
    }
}
