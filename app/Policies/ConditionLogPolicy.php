<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConditionLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConditionLogPolicy
{
    /**
     * Listing is a backstage oversight read across every record; per-record
     * ownership is still enforced by `view`.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, ConditionLog $conditionLog): Response
    {
        return $this->owns($user, $conditionLog);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, ConditionLog $conditionLog): Response
    {
        return $this->owns($user, $conditionLog);
    }

    public function delete(User $user, ConditionLog $conditionLog): Response
    {
        return $this->owns($user, $conditionLog);
    }

    /**
     * Bulk deletion is checked once rather than per record, so it stays a
     * whole-collection decision — the seam for the operator-role check.
     */
    public function deleteAny(User $user): Response
    {
        return Response::allow();
    }

    private function owns(User $user, ConditionLog $conditionLog): Response
    {
        return $conditionLog->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this condition log.');
    }
}
