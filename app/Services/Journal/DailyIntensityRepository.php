<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Read-side access to a user's daily condition intensity.
 *
 * A day can carry a rating per condition; the day's intensity is the **worst**
 * of them. Averaging would let a calm condition mask a severe one, which is the
 * opposite of what a journal is for.
 */
class DailyIntensityRepository
{
    /**
     * The user's worst rating per local calendar day, keyed by `Y-m-d`.
     *
     * Aggregated in one grouped query, so a month or a rolling window costs the
     * same regardless of how many conditions the user tracks.
     *
     * @return array<string, int>
     */
    public function worstPerDay(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return ConditionLog::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->selectRaw('date, max(intensity) as intensity')
            ->get()
            ->mapWithKeys(static fn (ConditionLog $log): array => [
                $log->date->toDateString() => $log->intensity,
            ])
            ->all();
    }
}
