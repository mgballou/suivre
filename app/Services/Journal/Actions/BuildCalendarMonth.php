<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Models\DailyCheckin;
use App\Models\User;
use App\Services\Journal\Data\CalendarDay;
use App\Services\Journal\Data\CalendarMonth;
use Carbon\CarbonImmutable;

class BuildCalendarMonth
{
    /**
     * Assemble the month grid for one user.
     *
     * Check-ins are read in a single range query scoped to the user, so the
     * grid costs one query no matter how many days it renders.
     *
     * `$today` is the user's local day (ResolveUserDay), never the server's.
     */
    public function __invoke(User $user, CarbonImmutable $month, CarbonImmutable $today): CalendarMonth
    {
        $start = $month->startOfMonth();
        $end = $start->endOfMonth();

        $loggedDates = $this->loggedDates($user, $start, $end);
        $todayDate = $today->toDateString();

        $days = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $hasCheckin = isset($loggedDates[$date]);

            $days[] = new CalendarDay(
                date: $date,
                level: $hasCheckin ? 1 : 0,
                hasCheckin: $hasCheckin,
                isToday: $date === $todayDate,
            );
        }

        return new CalendarMonth(
            month: $start->format('Y-m'),
            label: $start->format('F Y'),
            previousMonth: $start->subMonth()->format('Y-m'),
            nextMonth: $start->addMonth()->format('Y-m'),
            leadingBlanks: $start->dayOfWeekIso - 1,
            days: $days,
        );
    }

    /**
     * The user's check-in dates within the month, keyed by `Y-m-d` for lookup.
     *
     * @return array<string, int>
     */
    private function loggedDates(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dates = DailyCheckin::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date'])
            ->map(static fn (DailyCheckin $checkin): string => $checkin->date->toDateString())
            ->all();

        return array_flip($dates);
    }
}
