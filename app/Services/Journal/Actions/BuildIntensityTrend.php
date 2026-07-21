<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\DailyIntensityRepository;
use App\Services\Journal\Data\IntensityPoint;
use App\Services\Journal\Data\IntensityTrend;
use Carbon\CarbonImmutable;

class BuildIntensityTrend
{
    private const DEFAULT_WINDOW_DAYS = 30;

    public function __construct(
        private readonly DailyIntensityRepository $dailyIntensity,
    ) {}

    /**
     * Assemble a rolling window of daily intensity ending on the user's today.
     *
     * Every day in the window gets a point, logged or not — an unlogged day is
     * a null that breaks the line, never a zero that draws a healthy trough
     * where there is simply no data.
     */
    public function __invoke(
        User $user,
        CarbonImmutable $today,
        int $windowDays = self::DEFAULT_WINDOW_DAYS,
    ): IntensityTrend {
        $end = $today->startOfDay();
        $start = $end->subDays($windowDays - 1);

        $ratings = $this->dailyIntensity->worstPerDay($user, $start, $end);

        $points = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();

            $points[] = new IntensityPoint(
                date: $date,
                label: $cursor->format('j M'),
                intensity: $ratings[$date] ?? null,
            );
        }

        return new IntensityTrend(
            points: $points,
            loggedDays: count($ratings),
        );
    }
}
