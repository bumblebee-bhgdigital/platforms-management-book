<?php

namespace Platform\ManagementBook\Policies;

use Platform\Core\Authz\AuthzResolver;
use Platform\Core\Models\User;
use Platform\Core\Policies\RolePolicy;
use Platform\ManagementBook\Models\ManagementBook;

/**
 * Kein eigenes Rollensystem — gleiche Graph-Autorisierung wie PlannerProjectPolicy:
 * Ersteller (owns) ODER strukturell im Org-Graphen erreichbar (may). read < write < manage.
 * Zusätzlich darf der im Book eingetragene Owner (owner_user_id) lesen/ändern.
 * Teilen = das Book im Graphen (an einem Knoten) aufhängen.
 */
class ManagementBookPolicy extends RolePolicy
{
    public function view(User $user, $book): bool
    {
        return $this->graphAllows($user, $book, 'read');
    }

    public function update(User $user, $book): bool
    {
        return $this->graphAllows($user, $book, 'write');
    }

    public function delete(User $user, $book): bool
    {
        return $this->graphAllows($user, $book, 'manage');
    }

    public function forceDelete(User $user, $book): bool
    {
        return $this->graphAllows($user, $book, 'manage');
    }

    /** Aktivieren/Ablösen/Archivieren/Umhängen = Schreibrecht. */
    public function activate(User $user, $book): bool
    {
        return $this->update($user, $book);
    }

    public function create(User $user): bool
    {
        // Jedes Team-Mitglied kann Books anlegen (wird sein Ersteller).
        return $user->currentTeam !== null;
    }

    protected function graphAllows(User $user, $book, string $cap): bool
    {
        if (! $book || ! $book->id) {
            return false;
        }

        if ($book->owner_user_id && (int) $book->owner_user_id === (int) $user->id) {
            return true;
        }

        $resolver = app(AuthzResolver::class);
        $type = ManagementBook::class;

        return $resolver->may($user, $cap, $type, (int) $book->id)
            || $resolver->owns($user, $type, (int) $book->id);
    }

    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}
