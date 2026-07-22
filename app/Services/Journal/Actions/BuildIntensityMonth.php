<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Enums\RampStep;
use App\Models\User;
use App\Services\Journal\DailyIntensityRepository;
use App\Services\Journal\Data\IntensityDay;
use App\Services\Journal\Data\IntensityMonth;
use Carbon\CarbonImmutable;

class BuildIntensityMonth
{
    /**
     * Assemble one month of ramp steps for the calendar heatmap.
     *
     * `leadingBlanks` follows the same Monday-first ISO convention the month
     * grid uses, so both components line up under the same weekday header.
     */
    public function __invoke(User $user, CarbonImmutable $month): IntensityMonth
    {
        $start = $month->startOfMonth();
        $end = $start->endOfMonth();

        $ratings = app(DailyIntensityRepository::class)->worstPerDay($user, $start, $end);

        $days = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();

            $days[] = new IntensityDay(
                date: $date,
                level: RampStep::fromRating($ratings[$date] ?? null),
            );
        }

        return new IntensityMonth(
            month: $start->format('Y-m'),
            label: $start->format('F Y'),
            leadingBlanks: $start->dayOfWeekIso - 1,
            days: $days,
        );
    }
}
