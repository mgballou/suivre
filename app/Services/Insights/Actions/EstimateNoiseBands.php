<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\DayMask;
use App\Services\Insights\Data\NoiseBands;

class EstimateNoiseBands
{
    /**
     * The lift a report's tags reach by coincidence alone.
     *
     * SUI-36 gated detection on clearing a 95th-percentile noise band drawn
     * from tags known to be inert. A real user has no known-inert tags, so the
     * band is drawn from the tags themselves: every occurrence series is
     * rotated away from the intensity series and re-measured, once per offset.
     * Rotation keeps how often a tag fires and how it clumps — which matters,
     * because flares are sticky and an i.i.d. null would draw the band too low
     * — while destroying any real alignment.
     *
     * Every tag is rotated by the **same** offset, and the largest lift each
     * offset produces across the whole report is kept. That distribution is
     * what the page is gated on, because the page tests every measurable tag at
     * once: eight tags each checked against their own honest 5% band produce a
     * flagged row on two journals in five where nothing is a trigger. Rotating
     * together rather than independently also keeps the tags' real
     * co-occurrence intact, so the null is drawn from journals shaped like the
     * user's own.
     *
     * Both bands come out of one pass. Estimating them separately would double
     * the most expensive work the engine does to learn nothing new.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     * @param  array<int, DayMask>  $masks  one occurrence series per ranked row
     */
    public function __invoke(array $intensities, array $masks, int $windowDays): NoiseBands
    {
        $first = reset($masks);

        if ($first === false) {
            return NoiseBands::none();
        }

        /** @var array<int, array<int, float>> $lifts */
        $lifts = array_fill_keys(array_keys($masks), []);

        /** @var array<int, float> $maxima */
        $maxima = [];

        foreach ($this->offsets($first->length(), $windowDays) as $offset) {
            $largest = null;

            foreach ($masks as $row => $mask) {
                $lift = $this->rotatedLift($intensities, $mask, $offset, $windowDays);

                if ($lift === null) {
                    continue;
                }

                $lifts[$row][] = $lift;
                $largest = $largest === null ? $lift : max($largest, $lift);
            }

            if ($largest !== null) {
                $maxima[] = $largest;
            }
        }

        return new NoiseBands(
            perRow: array_map($this->band(...), $lifts),
            report: $this->band($maxima),
        );
    }

    /**
     * The lift one tag reaches with its occurrences shifted `$offset` days off
     * the ratings, or null where the shift leaves nothing to compare.
     *
     * @param  array<int, int|null>  $intensities
     */
    private function rotatedLift(array $intensities, DayMask $mask, int $offset, int $windowDays): ?float
    {
        $exposed = app(BuildExposureMask::class)($mask->rotate($offset), $windowDays);

        return app(MeasureLift::class)(
            $intensities,
            $exposed,
            $exposed->complement(),
            $mask->count(),
        )?->lift;
    }

    /**
     * The percentile of a null distribution, or null where too few offsets were
     * usable to draw one at all.
     *
     * @param  array<int, float>  $lifts
     */
    private function band(array $lifts): ?float
    {
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
