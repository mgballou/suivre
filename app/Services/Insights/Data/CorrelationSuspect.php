<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Enums\AttributionGranularity;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One row of the ranked "suspects" list.
 *
 * `granularity` is the load-bearing field (D24): a `SingleTag` suspect names
 * the one tag it carries, a `CoOccurrenceCluster` suspect names the whole
 * pattern its tags form and may never be phrased as an accusation of any one
 * member. `clearsNoiseBand` says whether the lift beat what the same tag
 * produces when its occurrences are shuffled away from the intensity series —
 * a gate on what is worth whispering, not a significance claim (D11 rules out
 * the false rigor of p-values at this sample size).
 *
 * @implements Arrayable<string, mixed>
 */
readonly class CorrelationSuspect implements Arrayable
{
    /**
     * @param  array<int, SuspectTag>  $tags
     */
    public function __construct(
        public AttributionGranularity $granularity,
        public array $tags,
        public LiftMeasurement $measurement,
        public LagProfile $lagProfile,
        public ?float $noiseBand,
        public bool $clearsNoiseBand,
    ) {}

    /**
     * @return array{
     *     granularity: string,
     *     tags: array<int, array{id: int, name: string, slug: string}>,
     *     measurement: array<string, mixed>,
     *     lagProfile: array<int, array{lag: int, lift: float|null, days: int}>,
     *     noiseBand: float|null,
     *     clearsNoiseBand: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'granularity' => $this->granularity->value,
            'tags' => array_map(
                static fn (SuspectTag $tag): array => $tag->toArray(),
                $this->tags,
            ),
            'measurement' => $this->measurement->toArray(),
            'lagProfile' => $this->lagProfile->toArray(),
            'noiseBand' => $this->noiseBand,
            'clearsNoiseBand' => $this->clearsNoiseBand,
        ];
    }
}
