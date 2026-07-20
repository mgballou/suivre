<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MealPolicy
{
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

    private function owns(User $user, Meal $meal): Response
    {
        return $meal->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this meal.');
    }
}
