<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;

class ResolveUserDay
{
    /**
     * Resolve the calendar day the given instant falls on in the user's timezone.
     */
    public function __invoke(User $user, CarbonImmutable $at): CarbonImmutable
    {
        return $at->setTimezone($user->timezone)->startOfDay();
    }
}
