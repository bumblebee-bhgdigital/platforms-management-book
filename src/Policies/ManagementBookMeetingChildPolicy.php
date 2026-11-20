<?php

namespace Platform\ManagementBook\Policies;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Models\User;
use Platform\ManagementBook\Models\ManagementBookMeeting;

/**
 * Basis für alle Sitzungs-Kinder (Agenda-Punkt, Unterlage, Protokoll, Beschluss, Teilnehmer):
 * sie erben die Rechte ihrer Sitzung (und damit Gremium und Management Book) —
 * lesen = Sitzung sichtbar, anlegen/ändern/löschen = Sitzung schreibbar.
 */
abstract class ManagementBookMeetingChildPolicy
{
    public function view(User $user, $model): bool
    {
        return $model?->meeting !== null && Gate::forUser($user)->allows('view', $model->meeting);
    }

    public function update(User $user, $model): bool
    {
        return $model?->meeting !== null && Gate::forUser($user)->allows('update', $model->meeting);
    }

    public function delete(User $user, $model): bool
    {
        return $this->update($user, $model);
    }

    /** Anlegen: Schreibrecht auf die Sitzung. */
    public function createFor(User $user, ManagementBookMeeting $meeting): bool
    {
        return Gate::forUser($user)->allows('update', $meeting);
    }
}
