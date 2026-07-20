<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The trigger taxonomy is global, operator-owned reference data (D9) — there is
 * no per-record ownership to check. While the MVP is single-user every
 * authenticated user is the operator, so curation is allowed outright; the seam
 * is here for the operator-role check that arrives with multi-user.
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
        return Response::allow();
    }

    public function update(User $user, Category $category): Response
    {
        return Response::allow();
    }

    public function delete(User $user, Category $category): Response
    {
        return Response::allow();
    }
}
