<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One lift estimate: mean condition intensity on a tag's exposed days minus the
 * mean on the days it is absent from (D11).
 *
 * `exposedDays`, `baselineDays` and `occurrences` travel with the number
 * because every figure Suivre shows states its own sample size — and because
 * SUI-36 finding 2 showed a thin ranking is dominated by noise, so a lift
 * without its `n` is not interpretable.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class LiftMeasurement implements Arrayable
{
    public function __construct(
        public float $lift,
        public float $cohensD,
        public float $exposedMean,
        public float $baselineMean,
        public int $exposedDays,
        public int $baselineDays,
        public int $occurrences,
    ) {}

    /**
     * @return array{
     *     lift: float,
     *     cohensD: float,
     *     exposedMean: float,
     *     baselineMean: float,
     *     exposedDays: int,
     *     baselineDays: int,
     *     occurrences: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'lift' => $this->lift,
            'cohensD' => $this->cohensD,
            'exposedMean' => $this->exposedMean,
            'baselineMean' => $this->baselineMean,
            'exposedDays' => $this->exposedDays,
            'baselineDays' => $this->baselineDays,
            'occurrences' => $this->occurrences,
        ];
    }
}
