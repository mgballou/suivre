<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A rolling window of daily intensity — the reference chart's payload.
 *
 * `loggedDays` is carried alongside the points because every figure Suivre
 * shows states its own sample size (D11): a trend drawn from four days is not
 * the same claim as one drawn from thirty. `windowDays` travels with it so the
 * denominator is the server's, not a number the page happens to also know.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class IntensityTrend implements Arrayable
{
    /**
     * @param  array<int, IntensityPoint>  $points
     */
    public function __construct(
        public array $points,
        public int $loggedDays,
        public int $windowDays,
    ) {}

    /**
     * @return array{
     *     points: array<int, array{date: string, label: string, values: array{intensity: int|null}}>,
     *     loggedDays: int,
     *     windowDays: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'points' => array_map(
                static fn (IntensityPoint $point): array => $point->toArray(),
                $this->points,
            ),
            'loggedDays' => $this->loggedDays,
            'windowDays' => $this->windowDays,
        ];
    }
}
