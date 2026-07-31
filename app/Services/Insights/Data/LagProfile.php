<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * How a tag's lift is distributed across the days that follow it.
 *
 * SUI-36 finding 5: the observed effect peaks around lag 3 and stays elevated
 * out to a week, so the engine returns the shape rather than collapsing it to
 * the single window D11 assumed. SUI-22 renders the curve; the headline lift on
 * the suspect is still measured over the configured window.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class LagProfile implements Arrayable
{
    /**
     * @param  array<int, LagPoint>  $points
     */
    public function __construct(public array $points) {}

    /**
     * The lag carrying the largest lift, or null when nothing was measurable.
     */
    public function peakLag(): ?int
    {
        $peakLag = null;
        $peakLift = null;

        foreach ($this->points as $point) {
            if ($point->lift === null) {
                continue;
            }

            if ($peakLift === null || $point->lift > $peakLift) {
                $peakLift = $point->lift;
                $peakLag = $point->lag;
            }
        }

        return $peakLag;
    }

    /**
     * @return array<int, array{lag: int, lift: float|null, days: int}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (LagPoint $point): array => $point->toArray(),
            $this->points,
        );
    }
}
