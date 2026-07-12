<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Condition;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConditionPolicy
{
    public function view(User $user, Condition $condition): Response
    {
        return $this->owns($user, $condition);
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

    private function owns(User $user, Condition $condition): Response
    {
        return $condition->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this condition.');
    }
}
