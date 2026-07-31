<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\ReviewStatus;
use App\Exceptions\Food\ReviewItemAlreadyDecidedException;
use App\Models\ReviewItem;

/**
 * Closes a queue item without touching the catalog.
 *
 * Not every miss deserves an entry. "a bit of leftover something", a typo, a
 * one-off nobody will type again — cataloguing those would dilute the trigram
 * index for everyone, so the honest outcome is to let the entry stay
 * unclassified and stop asking about it.
 *
 * The entry keeps its raw text and contributes nothing to correlation, which is
 * correct: the engine reads categories, and this text has none.
 */
class DismissReviewItem
{
    public function __invoke(ReviewItem $reviewItem): ReviewItem
    {
        throw_if(
            condition: ! $reviewItem->isOpen(),
            exception: ReviewItemAlreadyDecidedException::make($reviewItem),
        );

        $reviewItem->status = ReviewStatus::Dismissed;
        $reviewItem->save();

        return $reviewItem;
    }
}
