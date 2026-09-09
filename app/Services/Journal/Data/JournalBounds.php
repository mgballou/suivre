<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Carbon\CarbonImmutable;

/**
 * The inclusive range of local calendar days a user may journal against.
 *
 * `earliest` is the day their account began, `latest` is their own today —
 * both resolved in their timezone, so a user in Auckland may log the day a
 * server in UTC has not reached. Both are start-of-day instants, and the
 * comparison is on the calendar day rather than the instant, because a day is
 * what the journal records.
 *
 * The range is what makes the insights read bounded: the correlation engine
 * walks first rating to last rating one day at a time, so an unbounded date is
 * an unbounded page. Held here rather than re-derived at each write site.
 */
readonly class JournalBounds
{
    public function __construct(
        public CarbonImmutable $earliest,
        public CarbonImmutable $latest,
    ) {}

    public function covers(CarbonImmutable $date): bool
    {
        return ! $this->precedes($date) && ! $this->follows($date);
    }

    /** Whether the day falls before the journal began. */
    public function precedes(CarbonImmutable $date): bool
    {
        return $date->toDateString() < $this->earliest->toDateString();
    }

    /** Whether the day has not happened yet. */
    public function follows(CarbonImmutable $date): bool
    {
        return $date->toDateString() > $this->latest->toDateString();
    }
}
