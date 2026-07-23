<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\Data\DayMask;
use App\Services\Insights\Data\LiftMeasurement;

class MeasureLift
{
    /**
     * Compare mean condition intensity across two sets of days.
     *
     * Unrated days are absent from both means rather than counted as zero — a
     * gap in the journal is missing data, not a calm day. Cohen's `d` divides
     * the lift by the pooled standard deviation of the two groups, exactly as
     * the SUI-36 spike did, so the effect stays comparable across users whose
     * conditions swing by different amounts.
     *
     * Returns null when either side has no rated day at all: there is no lift
     * to state, and a zero would be a claim the data does not make.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     */
    public function __invoke(
        array $intensities,
        DayMask $exposed,
        DayMask $baseline,
        int $occurrences,
    ): ?LiftMeasurement {
        $exposedValues = $this->ratedValues($intensities, $exposed);
        $baselineValues = $this->ratedValues($intensities, $baseline);

        if ($exposedValues === [] || $baselineValues === []) {
            return null;
        }

        $exposedMean = array_sum($exposedValues) / count($exposedValues);
        $baselineMean = array_sum($baselineValues) / count($baselineValues);
        $lift = $exposedMean - $baselineMean;
        $pooled = $this->pooledStandardDeviation($exposedValues, $baselineValues);

        return new LiftMeasurement(
            lift: $lift,
            cohensD: $pooled > 0.0 ? $lift / $pooled : 0.0,
            exposedMean: $exposedMean,
            baselineMean: $baselineMean,
            exposedDays: count($exposedValues),
            baselineDays: count($baselineValues),
            occurrences: $occurrences,
        );
    }

    /**
     * The rated intensities falling on the masked days.
     *
     * @param  array<int, int|null>  $intensities
     * @return array<int, float>
     */
    private function ratedValues(array $intensities, DayMask $mask): array
    {
        $values = [];

        foreach ($mask->indexes() as $index) {
            $intensity = $intensities[$index] ?? null;

            if ($intensity !== null) {
                $values[] = (float) $intensity;
            }
        }

        return $values;
    }

    /**
     * @param  array<int, float>  $exposed
     * @param  array<int, float>  $baseline
     */
    private function pooledStandardDeviation(array $exposed, array $baseline): float
    {
        $exposedCount = count($exposed);
        $baselineCount = count($baseline);

        if ($exposedCount < 2 || $baselineCount < 2) {
            return 0.0;
        }

        $numerator = $this->sumOfSquares($exposed) + $this->sumOfSquares($baseline);

        return sqrt($numerator / ($exposedCount + $baselineCount - 2));
    }

    /**
     * @param  array<int, float>  $values
     */
    private function sumOfSquares(array $values): float
    {
        $mean = array_sum($values) / count($values);

        return array_sum(array_map(
            static fn (float $value): float => ($value - $mean) ** 2,
            $values,
        ));
    }
}
