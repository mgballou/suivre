<?php

declare(strict_types=1);

namespace App\Services\Conditions\Actions;

use App\Events\Conditions\ConditionRated;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Exceptions\Conditions\InvalidConditionIntensityException;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RateCondition
{
    /**
     * Record the user's 0–10 rating of a condition for a resolved local day,
     * upserting the single log that exists per (user, condition, date).
     */
    public function __invoke(User $user, Condition $condition, CarbonImmutable $date, int $intensity): ConditionLog
    {
        throw_if(
            condition: $condition->user_id !== $user->id,
            exception: ConditionNotOwnedException::make($user, $condition),
        );

        throw_if(
            condition: $intensity < 0 || $intensity > 10,
            exception: InvalidConditionIntensityException::make($intensity),
        );

        $log = DB::transaction(fn (): ConditionLog => ConditionLog::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'condition_id' => $condition->id,
                'date' => $date->startOfDay(),
            ],
            [
                'intensity' => $intensity,
            ],
        ));

        ConditionRated::dispatch($log);

        return $log;
    }
}
