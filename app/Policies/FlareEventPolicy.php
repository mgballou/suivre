<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FlareEvent;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FlareEventPolicy
{
    public function view(User $user, FlareEvent $flareEvent): Response
    {
        return $this->owns($user, $flareEvent);
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, FlareEvent $flareEvent): Response
    {
        return $this->owns($user, $flareEvent);
    }

    public function delete(User $user, FlareEvent $flareEvent): Response
    {
        return $this->owns($user, $flareEvent);
    }

    private function owns(User $user, FlareEvent $flareEvent): Response
    {
        return $flareEvent->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not own this flare event.');
    }
}
