<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How coarsely a ranked suspect may be named (D24, stage 1).
 *
 * Marginal per-tag lift over-accuses innocent foods that travel with a real
 * trigger — SUI-36 measured a zero-effect co-traveller landing top-3 61% of the
 * time, worsening rather than washing out with more data. So a suspect is
 * either a tag the data can defend on its own, or the whole cluster of tags
 * that move together, surfaced as one coarse pattern. The UI reads this to
 * choose its phrasing and must never accuse a single member of a cluster.
 */
enum AttributionGranularity: string
{
    case SingleTag = 'single_tag';
    case CoOccurrenceCluster = 'co_occurrence_cluster';

    /**
     * Whether the suspect names one tag the data separates from its neighbours.
     */
    public function isSingleTag(): bool
    {
        return $this === self::SingleTag;
    }

    /**
     * Whether the suspect names a pattern of tags that could not be pulled apart.
     */
    public function isCluster(): bool
    {
        return $this === self::CoOccurrenceCluster;
    }
}
