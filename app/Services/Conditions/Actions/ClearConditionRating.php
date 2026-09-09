<?php

declare(strict_types=1);

namespace App\Services\Conditions\Actions;

use App\Exceptions\Conditions\ConditionLogNotOwnedException;
use App\Models\ConditionLog;
use App\Models\User;

class ClearConditionRating
{
    /**
     * Remove a day's rating of one condition.
     *
     * The counterpart to `RateCondition`, which only ever upserts: without this
     * a rating written against a mistyped date could not be taken back, and the
     * insights page it lengthened could only be recovered with SQL.
     *
     * Deliberately unbounded by `JournalBounds` — the days it most needs to
     * reach are the ones the bound now refuses to write.
     */
    public function __invoke(User $user, ConditionLog $conditionLog): void
    {
        throw_if(
            condition: $conditionLog->user_id !== $user->id,
            exception: ConditionLogNotOwnedException::make($user, $conditionLog),
        );

        $conditionLog->delete();
    }
}
