<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\DayMask;

class EstimateNoiseBand
{
    /**
     * The lift this tag reaches by coincidence alone.
     *
     * SUI-36 gated detection on clearing a 95th-percentile noise band drawn
     * from tags known to be inert. A real user has no known-inert tags, so the
     * band is drawn from the tag itself: its occurrence series is rotated away
     * from the intensity series and re-measured, once per offset. Rotation
     * keeps how often the tag fires and how it clumps — which matters, because
     * flares are sticky and an i.i.d. null would draw the band too low — while
     * destroying any real alignment.
     *
     * Returns null when too few offsets are usable to draw a band at all; the
     * caller reports the tag as not clearing rather than clearing on a guess.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     */
    public function __invoke(array $intensities, DayMask $presence, int $windowDays): ?float
    {
        $offsets = $this->offsets($presence->length(), $windowDays);

        if (count($offsets) < CorrelationThresholds::MINIMUM_NOISE_BAND_SHIFTS) {
            return null;
        }

        $occurrences = $presence->count();
        $lifts = [];

        foreach ($offsets as $offset) {
            $rotated = $presence->rotate($offset);
            $exposed = app(BuildExposureMask::class)($rotated, $windowDays);
            $measurement = app(MeasureLift::class)(
                $intensities,
                $exposed,
                $exposed->complement(),
                $occurrences,
            );

            if ($measurement !== null) {
                $lifts[] = $measurement->lift;
            }
        }

        if (count($lifts) < CorrelationThresholds::MINIMUM_NOISE_BAND_SHIFTS) {
            return null;
        }

        return $this->percentile($lifts, CorrelationThresholds::NOISE_BAND_PERCENTILE);
    }

    /**
     * Rotations far enough from zero that the shifted series cannot still be
     * covering the days the real one did, evenly spaced and capped so the cost
     * stays flat as a user's history grows.
     *
     * @return array<int, int>
     */
    private function offsets(int $length, int $windowDays): array
    {
        $first = $windowDays + 1;
        $last = $length - $windowDays - 1;

        if ($last < $first) {
            return [];
        }

        $available = $last - $first + 1;
        $stride = max(1, (int) ceil($available / CorrelationThresholds::MAXIMUM_NOISE_BAND_SHIFTS));

        return range($first, $last, $stride);
    }

    /**
     * The linear-interpolated percentile of a sample, matching the convention
     * the spike's numpy percentiles used.
     *
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, float $percentile): float
    {
        sort($values);

        $rank = ($percentile / 100.0) * (count($values) - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        if ($lower === $upper) {
            return $values[$lower];
        }

        return $values[$lower] + ($rank - $lower) * ($values[$upper] - $values[$lower]);
    }
}
