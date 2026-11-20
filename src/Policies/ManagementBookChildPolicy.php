<?php

namespace Platform\ManagementBook\Policies;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Models\User;
use Platform\ManagementBook\Models\ManagementBook;

/**
 * Basis für alle Strategie-Entitäten (Mission/Vision, Forecast, Fokusraum, Zielbild, Hindernis, Meilenstein):
 * sie erben die Rechte ihres Management Books (kein eigenes Rollensystem) —
 * lesen = Book sichtbar, anlegen/ändern/löschen = Book schreibbar.
 */
abstract class ManagementBookChildPolicy
{
    public function view(User $user, $model): bool
    {
        $book = $this->book($model);

        return $book !== null && Gate::forUser($user)->allows('view', $book);
    }

    public function update(User $user, $model): bool
    {
        $book = $this->book($model);

        return $book !== null && Gate::forUser($user)->allows('update', $book);
    }

    public function delete(User $user, $model): bool
    {
        return $this->update($user, $model);
    }

    /** Anlegen: Schreibrecht auf das Book. */
    public function createFor(User $user, ManagementBook $book): bool
    {
        return Gate::forUser($user)->allows('update', $book);
    }

    protected function book($model): ?ManagementBook
    {
        return $model?->book;
    }
}
