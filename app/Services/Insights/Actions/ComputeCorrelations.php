<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Enums\AttributionGranularity;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Models\Condition;
use App\Models\User;
use App\Services\Insights\CorrelationDataRepository;
use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\CorrelationReport;
use App\Services\Insights\Data\CorrelationSuspect;
use App\Services\Insights\Data\DayMask;
use App\Services\Insights\Data\LiftMeasurement;
use App\Services\Insights\Data\SuspectTag;
use Carbon\CarbonImmutable;

/**
 * The lag-lift correlation engine (D11, reshaped by SUI-36 and D24).
 *
 * For one user and one condition it ranks the trigger categories whose recent
 * presence coincides with worse days: lift is the mean intensity on a tag's
 * exposed days minus the mean on the days it is absent from, exposure is the
 * per-day union of `t … t+window`, and every row carries its sample size. The
 * whole lag profile comes back alongside the headline number because the effect
 * demonstrably smears past any single window.
 *
 * Three things the spike forced into the design:
 *
 * - **Volume gate.** Under `MINIMUM_LOGGED_DAYS` the report is an explicit
 *   insufficient-data outcome, not a hedged ranking. Below 90 days even the
 *   softest single hint is right barely more than half the time.
 * - **Noise band.** Each suspect states whether its lift beat the 95th
 *   percentile of what the same tag produces when rotated away from the
 *   intensity series. This is a gate on what is worth whispering, not a
 *   significance test — D11 rules out the false rigor of p-values at this `n`.
 * - **Separability (D24, stage 1).** Tags that travel together are only named
 *   individually when the marginal lift survives being measured on the days
 *   they appear apart. The criterion chosen here: two tags are co-travellers
 *   when their occurrence days overlap by at least
 *   `CO_OCCURRENCE_OVERLAP` (Jaccard), and a co-traveller is separable only
 *   when it has at least `MINIMUM_DISCORDANT_DAYS` rated days without its
 *   partner, as many clean of both, and retains at least
 *   `SEPARABLE_LIFT_RETENTION` of its marginal lift on them. Anything that
 *   fails collapses into one coarse pattern suspect covering the whole cluster.
 *   The thresholds are documented on `CorrelationThresholds` and become Spatie
 *   runtime settings in E5 (SUI-25).
 *
 * Computed on demand — the MVP has no scheduled recompute, job, or cache (D11).
 */
class ComputeCorrelations
{
    /**
     * Rank the trigger categories suspected of preceding this condition's bad
     * days.
     *
     * "Logged days" — the volume the gate reads — is the number of distinct
     * local calendar days carrying a rating for **this** condition. Meals are
     * not counted: a lift is a comparison of rated days, so an unrated day
     * cannot contribute to either side of it however much was eaten on it.
     */
    public function __invoke(
        User $user,
        Condition $condition,
        int $windowDays = CorrelationThresholds::EXPOSURE_WINDOW_DAYS,
        int $lagProfileDays = CorrelationThresholds::LAG_PROFILE_DAYS,
    ): CorrelationReport {
        throw_if(
            condition: $condition->user_id !== $user->id,
            exception: ConditionNotOwnedException::make($user, $condition),
        );

        $intensityByDate = app(CorrelationDataRepository::class)->dailyIntensity($user, $condition);
        $loggedDays = count($intensityByDate);

        if ($loggedDays < CorrelationThresholds::MINIMUM_LOGGED_DAYS) {
            return CorrelationReport::insufficientData(
                loggedDays: $loggedDays,
                requiredDays: CorrelationThresholds::MINIMUM_LOGGED_DAYS,
                windowDays: $windowDays,
            );
        }

        $dates = array_keys($intensityByDate);
        $lead = max($windowDays, $lagProfileDays);
        $start = CarbonImmutable::parse($dates[0])->subDays($lead);
        $end = CarbonImmutable::parse($dates[$loggedDays - 1]);

        $days = $this->spanDays($start, $end);
        $intensities = array_map(
            static fn (string $date): ?int => $intensityByDate[$date] ?? null,
            $days,
        );

        $history = app(CorrelationDataRepository::class)->exposureHistory($user, $start, $end);
        $presence = $this->presenceMasks($days, $history->categoryIdsByDate);

        $measurements = $this->measurableTags($intensities, $presence, $windowDays);
        $presence = array_intersect_key($presence, $measurements);

        $clusters = app(GroupCoOccurringTags::class)(
            $intensities,
            $presence,
            array_map(static fn (LiftMeasurement $measurement): float => $measurement->lift, $measurements),
            $windowDays,
        );

        $suspects = [];

        foreach ($clusters->groups as $group) {
            $suspect = $this->buildSuspect(
                $intensities,
                $presence,
                $history->tags,
                $group,
                $windowDays,
                $lagProfileDays,
            );

            if ($suspect !== null) {
                $suspects[] = $suspect;
            }
        }

        usort(
            $suspects,
            static fn (CorrelationSuspect $a, CorrelationSuspect $b): int => $b->measurement->lift <=> $a->measurement->lift,
        );

        return CorrelationReport::ranked(
            suspects: $suspects,
            loggedDays: $loggedDays,
            requiredDays: CorrelationThresholds::MINIMUM_LOGGED_DAYS,
            windowDays: $windowDays,
        );
    }

    /**
     * Every local day from `$start` to `$end` inclusive, as `Y-m-d`.
     *
     * The span is contiguous even where the journal is not: a tag eaten on an
     * unrated day still exposes the rated days that follow it, so the index has
     * to advance one day at a time rather than one rating at a time.
     *
     * @return array<int, string>
     */
    private function spanDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $days[] = $cursor->toDateString();
        }

        return $days;
    }

    /**
     * A per-tag occurrence mask over the span.
     *
     * @param  array<int, string>  $days
     * @param  array<string, array<int, int>>  $categoryIdsByDate
     * @return array<int, DayMask>
     */
    private function presenceMasks(array $days, array $categoryIdsByDate): array
    {
        $occurrences = [];
        $length = count($days);

        foreach ($days as $index => $date) {
            foreach ($categoryIdsByDate[$date] ?? [] as $categoryId) {
                $occurrences[$categoryId][] = $index;
            }
        }

        ksort($occurrences);

        return array_map(
            static fn (array $indexes): DayMask => DayMask::of($length, $indexes),
            $occurrences,
        );
    }

    /**
     * The marginal lift of every tag with enough rated exposed and baseline
     * days to be worth a number at all. Tags below the floor are dropped rather
     * than ranked badly — SUI-36 finding 2 is that thin tags are exactly where
     * a personal-scale ranking goes wrong.
     *
     * @param  array<int, int|null>  $intensities
     * @param  array<int, DayMask>  $presence
     * @return array<int, LiftMeasurement>
     */
    private function measurableTags(array $intensities, array $presence, int $windowDays): array
    {
        $measurements = [];

        foreach ($presence as $categoryId => $mask) {
            $measurement = $this->measure($intensities, $mask, $windowDays);

            if ($measurement === null) {
                continue;
            }

            if ($measurement->exposedDays < CorrelationThresholds::MINIMUM_EXPOSED_DAYS) {
                continue;
            }

            if ($measurement->baselineDays < CorrelationThresholds::MINIMUM_BASELINE_DAYS) {
                continue;
            }

            $measurements[$categoryId] = $measurement;
        }

        return $measurements;
    }

    /**
     * @param  array<int, int|null>  $intensities
     */
    private function measure(array $intensities, DayMask $presence, int $windowDays): ?LiftMeasurement
    {
        $exposed = app(BuildExposureMask::class)($presence, $windowDays);

        return app(MeasureLift::class)(
            $intensities,
            $exposed,
            $exposed->complement(),
            $presence->count(),
        );
    }

    /**
     * Turn one cluster into a ranked row.
     *
     * A cluster of several tags is measured on the union of their occurrence
     * days: the pattern is "a day any of these appeared", which is what the
     * coarse phrasing D24 mandates ("meals with X and Y") actually describes.
     *
     * @param  array<int, int|null>  $intensities
     * @param  array<int, DayMask>  $presence
     * @param  array<int, SuspectTag>  $tags
     * @param  array<int, int>  $group
     */
    private function buildSuspect(
        array $intensities,
        array $presence,
        array $tags,
        array $group,
        int $windowDays,
        int $lagProfileDays,
    ): ?CorrelationSuspect {
        $mask = null;

        foreach ($group as $categoryId) {
            $mask = $mask === null ? $presence[$categoryId] : $mask->union($presence[$categoryId]);
        }

        if ($mask === null) {
            return null;
        }

        $measurement = $this->measure($intensities, $mask, $windowDays);

        if ($measurement === null) {
            return null;
        }

        $noiseBand = app(EstimateNoiseBand::class)($intensities, $mask, $windowDays);

        return new CorrelationSuspect(
            granularity: count($group) === 1
                ? AttributionGranularity::SingleTag
                : AttributionGranularity::CoOccurrenceCluster,
            tags: array_values(array_map(
                static fn (int $categoryId): SuspectTag => $tags[$categoryId],
                $group,
            )),
            measurement: $measurement,
            lagProfile: app(BuildLagProfile::class)($intensities, $mask, $lagProfileDays),
            noiseBand: $noiseBand,
            clearsNoiseBand: $noiseBand !== null && $measurement->lift > $noiseBand,
        );
    }
}
