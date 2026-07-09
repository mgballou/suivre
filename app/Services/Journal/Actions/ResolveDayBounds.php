<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\Data\DayBounds;
use Carbon\CarbonImmutable;

class ResolveDayBounds
{
    /**
     * Resolve the half-open instant interval covering the given calendar day
     * in the user's timezone.
     */
    public function __invoke(User $user, CarbonImmutable $date): DayBounds
    {
        $startsAt = CarbonImmutable::parse($date->toDateString(), $user->timezone)->startOfDay();

        return new DayBounds(
            startsAt: $startsAt,
            endsAt: $startsAt->addDay()->startOfDay(),
        );
    }
}
