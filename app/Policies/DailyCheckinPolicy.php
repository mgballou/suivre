<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DailyCheckin;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DailyCheckinPolicy
{
    /**
     * Listing is a backstage oversight read across every record; per-record
     * ownership is still enforced by `view`.
     */
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, DailyCheckin $checkin): Response
    {
        return $this->owns($user, $checkin);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, DailyCheckin $checkin): Response
    {
        return $this->owns($user, $checkin);
    }

    public function delete(User $user, DailyCheckin $checkin): Response
    {
        return $this->owns($user, $checkin);
    }

    /**
     * Bulk deletion is checked once rather than per record, so it stays a
     * whole-collection decision — the seam for the operator-role check.
     */
    public function deleteAny(User $user): Response
    {
        return Response::allow();
    }

    private function owns(User $user, DailyCheckin $checkin): Response
    {
        return $user->id === $checkin->user_id
            ? Response::allow()
            : Response::deny('You may only manage your own check-ins.');
    }
}
