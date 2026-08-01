<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accounts are inspected from the backstage, never created, edited or destroyed
 * there: registration owns creation, the user maintains their own profile, and
 * deleting a user would cascade away every meal, check-in and flare they ever
 * logged. Listing every account is administrator-only oversight; a user may only
 * ever see their own account.
 *
 * `resetPassword` and `setRole` are the two exceptions, and both are named
 * abilities rather than a relaxed `update` so that the general prohibition stays
 * intact and each has to be asked for. Neither touches anything a user recorded.
 */
class UserPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only administrators can list accounts.');
    }

    public function view(User $user, User $subject): Response
    {
        return $user->isAdmin() || $user->id === $subject->id
            ? Response::allow()
            : Response::deny('You may only view your own account.');
    }

    public function create(User $user): Response
    {
        return Response::deny('Accounts are created by registration, not the backstage.');
    }

    public function update(User $user, User $subject): Response
    {
        return Response::deny('Accounts are maintained by their owner, not the backstage.');
    }

    /**
     * The single, deliberately narrow exception to `update`.
     *
     * A password is a credential, not journal data, and the rule this policy
     * enforces is that the backstage never edits what a user recorded. Account
     * recovery is a different thing: without a mail transport wired up, an
     * administrator setting a password is the only way back in for someone who
     * has forgotten theirs. It touches nothing they logged.
     *
     * Kept as its own ability rather than relaxing `update`, so the general
     * prohibition stays intact and this exception has to be asked for by name.
     */
    public function resetPassword(User $user, User $subject): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only administrators can reset another account\'s password.');
    }

    /**
     * The second narrow exception to `update`, on the same reasoning as
     * `resetPassword`: a role is a grant this installation makes, not something
     * the account's owner recorded, and it is the only way a self-registered
     * account can be given backstage access — registration always mints a member.
     *
     * An administrator may not set their own role. Revoking your own admin would
     * lock you out of the one surface that could give it back, so the guard is
     * against the accident rather than against any malice.
     */
    public function setRole(User $user, User $subject): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Only administrators can set an account\'s role.');
        }

        return $user->id === $subject->id
            ? Response::deny('You cannot change your own role.')
            : Response::allow();
    }

    public function delete(User $user, User $subject): Response
    {
        return Response::deny('Deleting an account would cascade away its entire journal.');
    }

    public function deleteAny(User $user): Response
    {
        return Response::deny('Deleting an account would cascade away its entire journal.');
    }
}
