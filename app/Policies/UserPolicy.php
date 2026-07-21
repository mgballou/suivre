<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Accounts are inspected from the backstage, never created, edited or destroyed
 * there: registration owns creation, the user maintains their own profile, and
 * deleting a user would cascade away every meal, check-in and flare they ever
 * logged. While the MVP is single-user every authenticated user is the
 * operator; the seam for the operator-role check is here for when Suivre goes
 * multi-user.
 */
class UserPolicy
{
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, User $subject): Response
    {
        return Response::allow();
    }

    public function create(User $user): Response
    {
        return Response::deny('Accounts are created by registration, not the backstage.');
    }

    public function update(User $user, User $subject): Response
    {
        return Response::deny('Accounts are maintained by their owner, not the backstage.');
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
