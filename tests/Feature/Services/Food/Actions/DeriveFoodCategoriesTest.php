<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Actions;

use App\Services\Food\Actions\DeriveFoodCategories;
use App\Services\Food\Data\OpenFoodFactsProduct;

/**
 * @param  array<int, string>  $allergens
 * @param  array<int, string>  $ingredients
 * @param  array<int, string>  $additives
 * @param  array<int, string>  $categories
 */
function product(
    array $allergens = [],
    array $ingredients = [],
    array $additives = [],
    array $categories = [],
): OpenFoodFactsProduct {
    return new OpenFoodFactsProduct(
        code: '1',
        name: 'Test product',
        allergenTags: $allergens,
        ingredientTags: $ingredients,
        additiveTags: $additives,
        countryTags: [],
        categoryTags: $categories,
    );
}

it('derives an allergen category from the tag the export states', function (string $tag, string $slug): void {
    $derived = app(DeriveFoodCategories::class)(product(allergens: [$tag]));

    expect($derived->all())->toBe([$slug]);
})->with([
    ['en:milk', 'dairy'],
    ['en:gluten', 'gluten'],
    ['en:nuts', 'nuts'],
    ['en:peanuts', 'nuts'],
    ['en:soybeans', 'soy'],
    ['en:eggs', 'egg'],
]);

it('derives an ingredient category from the ingredient list', function (string $tag, string $slug): void {
    $derived = app(DeriveFoodCategories::class)(product(ingredients: [$tag]));

    expect($derived->all())->toBe([$slug]);
})->with([
    ['en:caffeine', 'caffeine'],
    ['en:coffee', 'caffeine'],
    ['en:cocoa', 'caffeine'],
    ['en:sugar', 'added-sugar'],
    ['en:glucose-syrup', 'added-sugar'],
    ['en:honey', 'added-sugar'],
    ['en:alcohol', 'alcohol'],
    ['en:red-wine', 'alcohol'],
]);

it('reads additives alongside ingredients, because the split means nothing to a trigger', function (): void {
    $derived = app(DeriveFoodCategories::class)(product(additives: ['en:caffeine']));

    expect($derived->all())->toBe(['caffeine']);
});

it('derives a trigger from the product category when the product type is the ingredient', function (string $tag, string $slug): void {
    $derived = app(DeriveFoodCategories::class)(product(categories: [$tag]));

    expect($derived->all())->toBe([$slug]);
})->with([
    ['en:coffees', 'caffeine'],
    ['en:teas', 'caffeine'],
    ['en:alcoholic-beverages', 'alcohol'],
]);

it('collects every trigger a product carries', function (): void {
    $derived = app(DeriveFoodCategories::class)(product(
        allergens: ['en:gluten'],
        ingredients: ['en:barley-malt', 'en:sugar'],
        categories: ['en:alcoholic-beverages'],
    ));

    expect($derived->all())->toBe(['added-sugar', 'alcohol', 'gluten']);
});

it('names each category once however many tags point at it', function (): void {
    $derived = app(DeriveFoodCategories::class)(product(
        ingredients: ['en:coffee', 'en:caffeine', 'en:cocoa'],
        categories: ['en:coffees'],
    ));

    expect($derived->all())->toBe(['caffeine']);
});

it('derives nothing from an untagged product', function (): void {
    $derived = app(DeriveFoodCategories::class)(product(ingredients: ['en:sea-salt']));

    expect($derived->all())->toBe([]);
});

it('never derives a research category the export cannot know', function (): void {
    // Tomato is a nightshade, but nothing in the export says so — the tag is a
    // research judgement, and D10 leaves it to curation.
    $derived = app(DeriveFoodCategories::class)(product(
        ingredients: ['en:tomatoes'],
        categories: ['en:sauces'],
    ));

    expect($derived->all())->toBe([]);
});

it('matches a trigger exactly, never as a substring', function (): void {
    $derived = app(DeriveFoodCategories::class)(product(ingredients: ['en:sugar-free-sweetener']));

    expect($derived->all())->toBe([]);
});
