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
        $endsAt = $startsAt->addDay()->startOfDay();

        /*
         * The arithmetic happens in the user's timezone — that is what makes a
         * daylight-saving day 23 or 25 hours long — but the bounds are handed
         * back in UTC, because they are used as query bindings.
         *
         * Laravel formats a Carbon binding in the Carbon's *own* timezone, with
         * no conversion. A local-zone bound therefore compares a wall-clock
         * string against UTC timestamp columns, and silently selects the wrong
         * window by the user's offset.
         */
        return new DayBounds(
            startsAt: $startsAt->utc(),
            endsAt: $endsAt->utc(),
        );
    }
}
