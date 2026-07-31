<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Services\Insights\Data\DayMask;

class BuildExposureMask
{
    /**
     * Mark the days a tag's effect could plausibly be showing on.
     *
     * Every occurrence at day `t` marks days `t … t+windowDays`, and the marks
     * are unioned: two occurrences three days apart with a window of three mark
     * seven distinct days, not eight. Counting per occurrence instead would
     * double-weight the days a frequently-eaten tag covers twice, which is the
     * per-day union rule D11 settled on and SUI-36 validated.
     */
    public function __invoke(DayMask $presence, int $windowDays): DayMask
    {
        $length = $presence->length();
        $exposed = [];

        foreach ($presence->indexes() as $index) {
            for ($offset = 0; $offset <= $windowDays; $offset++) {
                if ($index + $offset < $length) {
                    $exposed[] = $index + $offset;
                }
            }
        }

        return DayMask::of($length, $exposed);
    }
}
