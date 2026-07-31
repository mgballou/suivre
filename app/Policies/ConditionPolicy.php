<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Condition;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConditionPolicy
{
    /**
     * Listing is a backstage oversight read across every record; per-record,
     * `view` lets an administrator open any user's record and an owner their own.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, Condition $condition): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : $this->owns($user, $condition);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, Condition $condition): Response
    {
        return $this->owns($user, $condition);
    }

    public function delete(User $user, Condition $condition): Response
    {
        return $this->owns($user, $condition);
    }

    /**
     * Whether a daily rating or a flare may be recorded against this condition.
     *
     * Deactivating retires a condition without touching a single rating, so the
     * precondition is "still tracked", not "still exists" — and it lives here
     * rather than being re-derived by the day screen that renders the pickers.
     */
    public function record(User $user, Condition $condition): Response
    {
        $owns = $this->owns($user, $condition);

        if ($owns->denied()) {
            return $owns;
        }

        return $condition->is_active
            ? Response::allow()
            : Response::deny('You are no longer tracking this condition.');
    }

    private function owns(User $user, Condition $condition): Response
    {
        return $condition->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this condition.');
    }
}
