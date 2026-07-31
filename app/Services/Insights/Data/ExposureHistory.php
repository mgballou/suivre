<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

/**
 * Which trigger categories a user was exposed to on each of their local days.
 *
 * A day's tags are the distinct categories of every catalog food its classified
 * entries resolved to; an entry still awaiting classification carries no tags
 * and contributes nothing (D9). Days are keyed `Y-m-d` in the user's timezone,
 * resolved through `ResolveUserDay` rather than off the stored instant.
 */
readonly class ExposureHistory
{
    /**
     * @param  array<string, array<int, int>>  $categoryIdsByDate  keyed `Y-m-d`, values ascending category ids
     * @param  array<int, SuspectTag>  $tags  keyed by category id
     */
    public function __construct(
        public array $categoryIdsByDate,
        public array $tags,
    ) {}
}
