<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\Data\DayMask;
use App\Services\Insights\Data\LagPoint;
use App\Services\Insights\Data\LagProfile;

class BuildLagProfile
{
    /**
     * Measure the lift at each distance from a tag's occurrences.
     *
     * Every lag is compared against the same baseline — days no occurrence
     * reaches within `$maxLag` — so the points are on one scale and the shape
     * of the curve is readable. Unlike the headline lift this does not union
     * windows: lag `j` looks only at the day exactly `j` after each occurrence,
     * which is what makes the peak locatable at all.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     */
    public function __invoke(array $intensities, DayMask $presence, int $maxLag): LagProfile
    {
        $length = $presence->length();
        $occurrences = $presence->indexes();

        $baseline = app(BuildExposureMask::class)($presence, $maxLag)->complement();
        $baselineValues = [];

        foreach ($baseline->indexes() as $index) {
            $intensity = $intensities[$index] ?? null;

            if ($intensity !== null) {
                $baselineValues[] = (float) $intensity;
            }
        }

        $baselineMean = $baselineValues === []
            ? null
            : array_sum($baselineValues) / count($baselineValues);

        $points = [];

        for ($lag = 0; $lag <= $maxLag; $lag++) {
            $values = [];

            foreach ($occurrences as $occurrence) {
                $day = $occurrence + $lag;
                $intensity = $day < $length ? ($intensities[$day] ?? null) : null;

                if ($intensity !== null) {
                    $values[] = (float) $intensity;
                }
            }

            $lift = ($values === [] || $baselineMean === null)
                ? null
                : (array_sum($values) / count($values)) - $baselineMean;

            $points[] = new LagPoint(lag: $lag, lift: $lift, days: count($values));
        }

        return new LagProfile($points);
    }
}
