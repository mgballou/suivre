<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\Data\JournalBounds;
use Carbon\CarbonImmutable;

class ResolveJournalBounds
{
    /**
     * Resolve the range of local calendar days this user may journal against.
     *
     * The floor is the day the account began. Registration is closed and the
     * backstage mints every account (D27), so `created_at` is the day someone
     * was handed the app — there is no day before it they could have been
     * keeping this journal on. The ceiling is their own today: a day that has
     * not happened cannot be reported on.
     *
     * A clock that puts the account's first day after the user's today would
     * leave no day at all, so the floor never passes the ceiling.
     */
    public function __invoke(User $user, CarbonImmutable $now): JournalBounds
    {
        $resolveDay = app(ResolveUserDay::class);

        $latest = $resolveDay($user, $now);
        $earliest = $resolveDay($user, $user->created_at ?? $now);

        return new JournalBounds(
            earliest: $earliest->greaterThan($latest) ? $latest : $earliest,
            latest: $latest,
        );
    }
}
