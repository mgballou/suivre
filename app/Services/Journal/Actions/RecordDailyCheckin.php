<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Events\Journal\DailyCheckinRecorded;
use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordDailyCheckin
{
    /**
     * Upsert the user's check-in for the given local calendar day, keyed on
     * `(user_id, date)`, and announce it via a domain event on commit.
     */
    public function __invoke(
        User $user,
        CarbonImmutable $date,
        ?MoodLevel $mood,
        ?SleepQuality $sleep,
        ?StressLevel $stress,
        ?string $note,
    ): DailyCheckin {
        $checkin = DB::transaction(fn (): DailyCheckin => $user->dailyCheckins()->updateOrCreate(
            ['date' => $date->startOfDay()],
            [
                'mood' => $mood,
                'sleep' => $sleep,
                'stress' => $stress,
                'note' => $note,
            ],
        ));

        DailyCheckinRecorded::dispatch($checkin);

        return $checkin;
    }
}
