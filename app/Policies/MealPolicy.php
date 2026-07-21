<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MealPolicy
{
    /**
     * Listing is a backstage oversight read across every record; per-record
     * ownership is still enforced by `view`.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, Meal $meal): Response
    {
        return $this->owns($user, $meal);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, Meal $meal): Response
    {
        return $this->owns($user, $meal);
    }

    public function delete(User $user, Meal $meal): Response
    {
        return $this->owns($user, $meal);
    }

    /**
     * Bulk deletion is checked once rather than per record, so it stays a
     * whole-collection decision — the seam for the operator-role check.
     */
    public function deleteAny(User $user): Response
    {
        return Response::allow();
    }

    private function owns(User $user, Meal $meal): Response
    {
        return $meal->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this meal.');
    }
}
