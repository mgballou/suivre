<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The classification queue is operator-only, all the way down to reading it.
 *
 * Unlike the trigger taxonomy — global reference data every user's app resolves
 * against — a queue item holds the raw text somebody typed into their own food
 * journal. That is another person's health record in free-text form, so `view`
 * is gated on admin here rather than allowed to all.
 *
 * Deciding is additionally gated on the item still being open, so the policy is
 * the single place that knows a closed item cannot be re-decided; the Filament
 * action hides itself from the same predicate rather than restating it.
 */
class ReviewItemPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->operate($user);
    }

    public function view(User $user, ReviewItem $reviewItem): Response
    {
        return $this->operate($user);
    }

    public function decide(User $user, ReviewItem $reviewItem): Response
    {
        if (! $user->isAdmin()) {
            return $this->operate($user);
        }

        return $reviewItem->isOpen()
            ? Response::allow()
            : Response::deny('This entry has already been decided.');
    }

    private function operate(User $user): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only administrators can work the classification queue.');
    }
}
