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

        return $date->toDateString() === $today->toDateString()
            ? $now->setTimezone($user->timezone)
            : CarbonImmutable::parse($date->toDateString(), $user->timezone)->addHours(12);
    }
}
