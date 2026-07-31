<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\DayMask;

class CheckTagsAreSeparable
{
    /**
     * Whether `$subject`'s lift can be defended without `$partner` (D24).
     *
     * The test is the spike's `stratified_lift`: measure the subject only on
     * the days it appears and the partner does not, against days clean of both.
     * A tag carrying an effect of its own keeps most of its lift there; a tag
     * merely riding on its neighbour collapses toward zero, which is precisely
     * the over-accusation SUI-36 measured at 61%.
     *
     * Two ways to fail, and both mean "do not name this tag alone":
     *
     * 1. Too few discordant or clean days to estimate anything — the answer is
     *    unknown, and an unknown is not a licence to accuse.
     * 2. The stratified lift keeps less than `SEPARABLE_LIFT_RETENTION` of the
     *    marginal lift.
     *
     * A subject whose marginal lift is not positive is separable by default:
     * there is no accusation to defend.
     *
     * @param  array<int, int|null>  $intensities  indexed by day, null where unrated
     */
    public function __invoke(
        array $intensities,
        DayMask $subject,
        DayMask $partner,
        float $marginalLift,
        int $windowDays,
    ): bool {
        if ($marginalLift <= 0.0) {
            return true;
        }

        $subjectExposed = app(BuildExposureMask::class)($subject, $windowDays);
        $partnerExposed = app(BuildExposureMask::class)($partner, $windowDays);

        $discordant = $subjectExposed->without($partnerExposed);
        $clean = $subjectExposed->complement()->without($partnerExposed);

        $measurement = app(MeasureLift::class)(
            $intensities,
            $discordant,
            $clean,
            $subject->count(),
        );

        if ($measurement === null) {
            return false;
        }

        if ($measurement->exposedDays < CorrelationThresholds::MINIMUM_DISCORDANT_DAYS) {
            return false;
        }

        if ($measurement->baselineDays < CorrelationThresholds::MINIMUM_DISCORDANT_DAYS) {
            return false;
        }

        return $measurement->lift >= $marginalLift * CorrelationThresholds::SEPARABLE_LIFT_RETENTION;
    }
}
