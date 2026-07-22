<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;
use App\Services\Journal\Data\DayView;
use App\Services\Journal\Data\ScaleOption;
use Carbon\CarbonImmutable;

class BuildDayView
{
    /**
     * Assemble the day's check-in surface for one user.
     *
     * `$today` is the user's local day (ResolveUserDay), never the server's.
     */
    public function __invoke(User $user, CarbonImmutable $date, CarbonImmutable $today): DayView
    {
        $checkin = $user->dailyCheckins()
            ->where('date', $date->toDateString())
            ->first();

        return new DayView(
            date: $date->toDateString(),
            label: $date->format('l j F Y'),
            month: $date->format('Y-m'),
            level: $checkin instanceof DailyCheckin ? 1 : 0,
            isToday: $date->toDateString() === $today->toDateString(),
            mood: $checkin?->mood?->value,
            sleep: $checkin?->sleep?->value,
            stress: $checkin?->stress?->value,
            note: $checkin?->note,
            scales: [
                'mood' => $this->options(MoodLevel::ordered()),
                'sleep' => $this->options(SleepQuality::ordered()),
                'stress' => $this->options(StressLevel::ordered()),
            ],
        );
    }

    /**
     * @param  array<int, MoodLevel|SleepQuality|StressLevel>  $cases
     * @return array<int, ScaleOption>
     */
    private function options(array $cases): array
    {
        return array_map(
            static fn (MoodLevel|SleepQuality|StressLevel $case): ScaleOption => new ScaleOption(
                value: $case->value,
                label: $case->getLabel(),
            ),
            $cases,
        );
    }
}
