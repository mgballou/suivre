<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\FoodItemType;
use App\Models\FoodItem;
use App\Services\Food\Data\OpenFoodFactsProduct;

/**
 * Folds one Open Food Facts product into the catalog, creating the row or
 * merging into the one already standing for that food.
 *
 * Dedup is on `normalized_name`, not barcode. The catalog is a name-to-tags
 * knowledge base, not a product database: a user types "whole milk", and the
 * fifty barcodes the export holds for it are one catalog entry carrying the
 * union of what each of them knew. Keeping them apart would split the trigram
 * match fifty ways and teach the correlation engine nothing extra.
 *
 * `source_ref` therefore records the barcode of whichever product created the
 * row, not every barcode that fed it. It is provenance for the row's existence;
 * the tags are the part that accumulates.
 */
class ImportFoodProduct
{
    /**
     * @param  array<string, int>  $categoryIds  Category id keyed by slug, loaded once per run.
     */
    public function __invoke(OpenFoodFactsProduct $product, array $categoryIds): FoodItem
    {
        $foodItem = $this->resolveFoodItem($product);

        $slugs = app(DeriveFoodCategories::class)($product)->all();

        $derived = array_values(array_intersect_key($categoryIds, array_flip($slugs)));

        if ($derived !== []) {
            /*
             * Additive on purpose. An operator may have curated this food with
             * research categories the export cannot see, and a later import
             * must not quietly strip them — D10 keeps the curated taxonomy
             * authoritative over anything a dataset asserts.
             */
            $foodItem->categories()->syncWithoutDetaching($derived);
        }

        return $foodItem;
    }

    private function resolveFoodItem(OpenFoodFactsProduct $product): FoodItem
    {
        $existing = FoodItem::query()
            ->where('normalized_name', FoodItem::normalizeName($product->name))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $foodItem = new FoodItem();

        $foodItem->fill([
            'name' => $product->name,
            'type' => FoodItemType::Item,
            'source' => config()->string('food.catalog.source'),
            'source_ref' => $product->code,
        ]);

        $foodItem->save();

        return $foodItem;
    }
}
