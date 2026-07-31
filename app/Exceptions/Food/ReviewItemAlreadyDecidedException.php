<?php

declare(strict_types=1);

namespace App\Exceptions\Food;

use App\Models\ReviewItem;
use DomainException;

/**
 * Thrown when an operator acts on a queue item someone has already closed.
 *
 * Resolving grows the catalog, so acting twice would add a second alias and
 * re-link an entry that is already linked. The queue is worked by hand from a
 * page that may have been open for a while, which makes the double decision a
 * matter of when rather than if.
 */
class ReviewItemAlreadyDecidedException extends DomainException
{
    public static function make(ReviewItem $reviewItem): self
    {
        return new self(
            "Review item [{$reviewItem->id}] was already {$reviewItem->status->value}."
        );
    }
}
