<?php

declare(strict_types=1);

namespace App\Services\Conditions\Actions;

use App\Models\User;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;

class ResolveFlareMoment
{
    /**
     * The instant a flare logged from a day screen is recorded at.
     *
     * A flare is an acute event, so its time matters — but it is logged
     * mid-flare from a screen that names a *day*, not a moment. Logging today
     * therefore stamps the actual time; back-filling an earlier day, where the
     * real time is already lost, stamps midday so the event cannot drift into a
     * neighbouring day when read back in another timezone.
     */
    public function __invoke(User $user, CarbonImmutable $date, CarbonImmutable $now): CarbonImmutable
    {
        $today = app(ResolveUserDay::class)($user, $now);

        $moment = $date->toDateString() === $today->toDateString()
            ? $now
            : CarbonImmutable::parse($date->toDateString(), $user->timezone)->addHours(12);

        /*
         * Returned in UTC, and that is load-bearing. Eloquent's datetime cast
         * writes a Carbon's wall-clock reading and then reads it back as the
         * app timezone, so handing it 21:00+12:00 stores the instant 21:00 UTC
         * — the flare silently jumps twelve hours and can land on the wrong
         * day. The user's timezone decides *which* instant; it is not part of
         * it, and the day screen converts back for display.
         */
        return $moment->utc();
    }
}
