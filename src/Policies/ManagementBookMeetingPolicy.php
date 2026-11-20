<?php

namespace Platform\ManagementBook\Policies;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Models\User;
use Platform\ManagementBook\Models\ManagementBookCommittee;

/**
 * Sitzungen erben die Rechte ihres Gremiums (und damit des Management Books):
 * lesen = Gremium sichtbar, anlegen/ändern/löschen = Gremium schreibbar.
 */
class ManagementBookMeetingPolicy
{
    public function view(User $user, $meeting): bool
    {
        return $meeting?->committee !== null && Gate::forUser($user)->allows('view', $meeting->committee);
    }

    public function update(User $user, $meeting): bool
    {
        return $meeting?->committee !== null && Gate::forUser($user)->allows('update', $meeting->committee);
    }

    public function delete(User $user, $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    /** Anlegen: Schreibrecht auf das Gremium. */
    public function createFor(User $user, ManagementBookCommittee $committee): bool
    {
        return Gate::forUser($user)->allows('update', $committee);
    }
}
