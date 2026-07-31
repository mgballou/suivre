<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\FoodItemType;
use App\Models\FoodItem;
use App\Models\ReviewItem;
use Illuminate\Support\Facades\DB;

/**
 * Adds a new food to the catalog and settles the queue item that prompted it.
 *
 * The other half of D9's human-in-the-loop: `ResolveReviewItem` handles "the
 * catalog already knows this, under another name", and this handles "the catalog
 * has never heard of it". Curation is the only way a research category —
 * histamine, nightshade, FODMAP — ever reaches a food, since no dataset can
 * supply them (D10/D26).
 *
 * The row is created as curated rather than imported: it carries no `source`,
 * so a later catalog import cannot claim provenance for something a person
 * decided.
 */
class CatalogReviewItem
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function __invoke(ReviewItem $reviewItem, string $name, array $categoryIds = []): FoodItem
    {
        return DB::transaction(function () use ($reviewItem, $name, $categoryIds): FoodItem {
            $foodItem = new FoodItem();

            $foodItem->fill([
                'name' => $name,
                'type' => FoodItemType::Item,
            ]);

            $foodItem->save();

            if ($categoryIds !== []) {
                $foodItem->categories()->sync($categoryIds);
            }

            app(ResolveReviewItem::class)($reviewItem, $foodItem);

            return $foodItem;
        });
    }
}
