<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\ReviewStatus;
use App\Exceptions\Food\ReviewItemAlreadyDecidedException;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\ReviewItem;
use Illuminate\Support\Facades\DB;

/**
 * Settles a queued classification miss against a catalog food, and teaches the
 * classifier what it got wrong (D9).
 *
 * Two things happen, and the second is the point. Linking the entry fixes this
 * one meal. Recording the raw text as an **alias** on the matched food is what
 * makes the queue shrink over time: the next person to type "aunt bettys slice"
 * is matched by the trigram index rather than landing back here. A resolve that
 * only linked the entry would leave the operator answering the same question
 * forever.
 */
class ResolveReviewItem
{
    public function __invoke(ReviewItem $reviewItem, FoodItem $foodItem): ReviewItem
    {
        throw_if(
            condition: ! $reviewItem->isOpen(),
            exception: ReviewItemAlreadyDecidedException::make($reviewItem),
        );

        DB::transaction(function () use ($reviewItem, $foodItem): void {
            $this->linkEntry($reviewItem, $foodItem);
            $this->teachTheClassifier($reviewItem, $foodItem);

            $reviewItem->status = ReviewStatus::Resolved;
            $reviewItem->save();
        });

        return $reviewItem;
    }

    private function linkEntry(ReviewItem $reviewItem, FoodItem $foodItem): void
    {
        $entry = $reviewItem->reviewable;

        // Only a FoodEntry is reviewable today. Guarding rather than asserting
        // keeps a future reviewable type from silently writing to a column it
        // does not have.
        if (! $entry instanceof FoodEntry) {
            return;
        }

        $entry->food_item_id = $foodItem->id;
        $entry->save();
    }

    private function teachTheClassifier(ReviewItem $reviewItem, FoodItem $foodItem): void
    {
        $normalized = FoodItem::normalizeName($reviewItem->text);

        // Nothing to learn when the text already normalizes to the food's own
        // name — the classifier would have matched it on the canonical name, so
        // an alias would be a duplicate index entry for no gain.
        if ($normalized === '' || $normalized === $foodItem->normalized_name) {
            return;
        }

        $exists = FoodItemAlias::query()
            ->where('food_item_id', $foodItem->id)
            ->where('normalized_alias', $normalized)
            ->exists();

        if ($exists) {
            return;
        }

        $alias = new FoodItemAlias();

        $alias->fill([
            'food_item_id' => $foodItem->id,
            'alias' => $reviewItem->text,
        ]);

        $alias->save();
    }
}
