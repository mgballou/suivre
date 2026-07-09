<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Carbon\CarbonImmutable;

/**
 * The half-open instant interval covering one of a user's local calendar days.
 *
 * `startsAt` is inclusive, `endsAt` is exclusive: an instant belongs to the day
 * when `startsAt <= $instant < $endsAt`. The interval is 23, 24, or 25 hours
 * long depending on daylight-saving transitions.
 */
readonly class DayBounds
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
