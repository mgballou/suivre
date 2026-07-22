<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The trigger taxonomy is global, operator-owned reference data (D9) — there is
 * no per-record ownership to check. Every user reads it (the app resolves a meal's
 * categories against it), but only administrators curate it from the backstage.
 */
class CategoryPolicy
{
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, Category $category): Response
    {
        return Response::allow();
    }

    public function create(User $user): Response
    {
        return $this->curate($user);
    }

    public function update(User $user, Category $category): Response
    {
        return $this->curate($user);
    }

    public function deleteAny(User $user): Response
    {
        return $this->curate($user);
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->curate($user);
    }

    private function curate(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only administrators can curate the trigger taxonomy.');
    }
}
