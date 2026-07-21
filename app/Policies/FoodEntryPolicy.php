<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FoodEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A food entry has no owner of its own — it inherits ownership from the meal
 * it belongs to, which is the single source of truth for the check.
 */
class FoodEntryPolicy
{
    /**
     * Listing is a backstage oversight read across every record; per-record
     * ownership is still enforced by `view`.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, FoodEntry $foodEntry): Response
    {
        return $this->owns($user, $foodEntry);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, FoodEntry $foodEntry): Response
    {
        return $this->owns($user, $foodEntry);
    }

    public function delete(User $user, FoodEntry $foodEntry): Response
    {
        return $this->owns($user, $foodEntry);
    }

    /**
     * Bulk deletion is checked once rather than per record, so it stays a
     * whole-collection decision — the seam for the operator-role check.
     */
    public function deleteAny(User $user): Response
    {
        return Response::allow();
    }

    private function owns(User $user, FoodEntry $foodEntry): Response
    {
        $foodEntry->loadMissing('meal');

        return $foodEntry->meal->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this food entry.');
    }
}
