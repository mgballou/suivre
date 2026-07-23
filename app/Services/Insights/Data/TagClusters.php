<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

/**
 * The partition of a user's measurable tags into what may be named alone and
 * what may only be named as a pattern (D24).
 *
 * A group of one is a tag whose lift survived being measured on the days it
 * appeared without each of its co-travellers. A group of two or more is a
 * cluster the data could not pull apart at this sample size.
 */
readonly class TagClusters
{
    /**
     * @param  array<int, array<int, int>>  $groups  each an ascending list of category ids
     */
    public function __construct(public array $groups) {}
}
