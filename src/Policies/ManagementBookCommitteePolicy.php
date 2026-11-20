<?php

namespace Platform\ManagementBook\Policies;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Models\User;
use Platform\ManagementBook\Models\ManagementBook;

/**
 * Gremien erben die Rechte ihres Management Books (kein eigenes Rollensystem):
 * lesen = Book sichtbar, anlegen/ändern = Book schreibbar, löschen = Book-Schreibrecht.
 */
class ManagementBookCommitteePolicy
{
    public function view(User $user, $committee): bool
    {
        return $this->book($committee) !== null && Gate::forUser($user)->allows('view', $this->book($committee));
    }

    public function update(User $user, $committee): bool
    {
        return $this->book($committee) !== null && Gate::forUser($user)->allows('update', $this->book($committee));
    }

    public function delete(User $user, $committee): bool
    {
        return $this->update($user, $committee);
    }

    /** Anlegen: Schreibrecht auf das Book, an dem das Gremium hängt. */
    public function createFor(User $user, ManagementBook $book): bool
    {
        return Gate::forUser($user)->allows('update', $book);
    }

    protected function book($committee): ?ManagementBook
    {
        return $committee?->book;
    }
}
