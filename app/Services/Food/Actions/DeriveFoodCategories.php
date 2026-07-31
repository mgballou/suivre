<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Services\Food\Data\OpenFoodFactsProduct;
use Illuminate\Support\Collection;

/**
 * Turns an Open Food Facts product's structured tags into curated Category
 * slugs — the "auto-derive the easy tags" half of D10.
 *
 * Matching is exact membership of a hand-written list, never substring or
 * prefix guessing. That is the same commitment D9 makes about the classifier:
 * an operator looking at a tagged food must be able to point at the rule that
 * put it there. A list that is too short under-tags visibly, and the review
 * queue corrects it; a substring rule that quietly tags "sugar-free" as added
 * sugar corrects nothing, because nobody can see it happening.
 *
 * Only the allergen and ingredient groups appear here. Research categories —
 * histamine, nightshade, FODMAP — have no equivalent in the export and stay
 * curated (D10); the import is additionally prevented from assigning them,
 * because `ImportOpenFoodFacts` only ever hands over the auto-derivable slugs.
 *
 * Expect thin results on real data. Open Food Facts has far better name coverage
 * than tag coverage — most products carry a name and no allergen data at all —
 * which is the caveat D10 accepts: an untagged catalog entry still earns its
 * place by making entry and autocomplete work.
 */
class DeriveFoodCategories
{
    /**
     * Allergen slugs the export states directly. `allergens_tags` means the
     * product contains the allergen; `traces_tags` — deliberately not read —
     * means it might, which is a manufacturing warning rather than an exposure
     * and would poison a correlation with food the user never ate.
     *
     * @var array<string, array<int, string>>
     */
    private const array ALLERGEN_TRIGGERS = [
        'dairy' => ['en:milk'],
        'gluten' => ['en:gluten'],
        'nuts' => ['en:nuts', 'en:peanuts'],
        'soy' => ['en:soybeans'],
        'egg' => ['en:eggs'],
    ];

    /**
     * Ingredient and additive slugs, read from both lists at once because the
     * export files caffeine under ingredients and its E-numbered relatives under
     * additives, and the distinction means nothing to a trigger category.
     *
     * @var array<string, array<int, string>>
     */
    private const array INGREDIENT_TRIGGERS = [
        'caffeine' => [
            'en:caffeine', 'en:coffee', 'en:coffee-extract', 'en:instant-coffee',
            'en:tea', 'en:black-tea', 'en:green-tea', 'en:tea-extract',
            'en:cocoa', 'en:cocoa-powder', 'en:cocoa-mass', 'en:chocolate',
            'en:dark-chocolate', 'en:guarana', 'en:cola-nut', 'en:kola-nut',
            'en:mate', 'en:yerba-mate',
        ],
        'added-sugar' => [
            'en:sugar', 'en:sugars', 'en:cane-sugar', 'en:brown-sugar',
            'en:caster-sugar', 'en:beet-sugar', 'en:invert-sugar',
            'en:invert-sugar-syrup', 'en:glucose', 'en:glucose-syrup',
            'en:glucose-fructose-syrup', 'en:fructose', 'en:fructose-syrup',
            'en:high-fructose-corn-syrup', 'en:corn-syrup', 'en:dextrose',
            'en:maltose', 'en:molasses', 'en:honey', 'en:maple-syrup',
            'en:agave-syrup', 'en:golden-syrup', 'en:treacle',
        ],
        'alcohol' => [
            'en:alcohol', 'en:ethanol', 'en:ethyl-alcohol', 'en:wine',
            'en:white-wine', 'en:red-wine', 'en:cooking-wine', 'en:beer',
            'en:cider', 'en:rum', 'en:whisky', 'en:whiskey', 'en:brandy',
            'en:vodka', 'en:gin', 'en:liqueur', 'en:sherry', 'en:port-wine',
            'en:kirsch',
        ],
    ];

    /**
     * Product-category slugs that carry the trigger even when the ingredient
     * list omits it — a bottle of beer rarely lists ethanol, and a bag of
     * ground coffee rarely lists coffee.
     *
     * Held to the two categories where the product type *is* the ingredient.
     * Nothing here infers a trigger from a category that merely tends to carry
     * it, so "en:sodas" is absent: most are sweetened, but the rule would be
     * guessing rather than reading.
     *
     * @var array<string, array<int, string>>
     */
    private const array CATEGORY_TRIGGERS = [
        'caffeine' => ['en:coffees', 'en:teas', 'en:colas', 'en:energy-drinks'],
        'alcohol' => ['en:alcoholic-beverages'],
    ];

    /**
     * @return Collection<int, string>
     */
    public function __invoke(OpenFoodFactsProduct $product): Collection
    {
        $ingredientTags = array_merge($product->ingredientTags, $product->additiveTags);

        return $this->triggered(self::ALLERGEN_TRIGGERS, $product->allergenTags)
            ->merge($this->triggered(self::INGREDIENT_TRIGGERS, $ingredientTags))
            ->merge($this->triggered(self::CATEGORY_TRIGGERS, $product->categoryTags))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @param  array<string, array<int, string>>  $triggers
     * @param  array<int, string>  $tags
     * @return Collection<int, string>
     */
    private function triggered(array $triggers, array $tags): Collection
    {
        return collect($triggers)
            ->filter(static fn (array $needles): bool => array_intersect($needles, $tags) !== [])
            ->keys();
    }
}
