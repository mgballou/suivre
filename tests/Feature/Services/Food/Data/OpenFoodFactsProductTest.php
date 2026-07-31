<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Data;

use App\Services\Food\Data\OpenFoodFactsProduct;

it('reads the fields the catalog needs and ignores the rest', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine(json_encode([
        'code' => '3017620422003',
        'product_name' => 'Whole milk',
        'allergens_tags' => ['en:milk'],
        'ingredients_tags' => ['en:milk'],
        'additives_tags' => ['en:e330'],
        'countries_tags' => ['en:united-kingdom'],
        'categories_tags' => ['en:dairies'],
        'nutriments' => ['energy-kj_100g' => 268],
    ], flags: JSON_THROW_ON_ERROR));

    expect($product)->not->toBeNull();
    expect($product?->code)->toBe('3017620422003');
    expect($product?->name)->toBe('Whole milk');
    expect($product?->allergenTags)->toBe(['en:milk']);
    expect($product?->additiveTags)->toBe(['en:e330']);
});

it('falls back to the english name when the default one is missing', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name_en":"Rice cakes"}');

    expect($product?->name)->toBe('Rice cakes');
});

it('lowercases tags so a comparison never turns on the export casing', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Milk","allergens_tags":["EN:Milk"]}');

    expect($product?->allergenTags)->toBe(['en:milk']);
});

it('drops tag entries that are not strings rather than failing the whole line', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Milk","allergens_tags":["en:milk",null,42]}');

    expect($product?->allergenTags)->toBe(['en:milk']);
});

it('treats a tag field that is not a list as absent', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Milk","allergens_tags":"en:milk"}');

    expect($product?->allergenTags)->toBe([]);
});

it('rejects a line that cannot become a catalog entry', function (string $line): void {
    expect(OpenFoodFactsProduct::fromJsonLine($line))->toBeNull();
})->with([
    'malformed json' => ['{"code":"1", not json'],
    'no barcode' => ['{"code":"","product_name":"Orphan"}'],
    'no name in any language' => ['{"code":"1","product_name":""}'],
    'a name that normalizes away entirely' => ['{"code":"1","product_name":"---"}'],
]);

it('matches a country filter written with or without the locale prefix', function (string $filter): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Milk","countries_tags":["en:united-kingdom"]}');

    expect($product?->isFromCountry($filter))->toBeTrue();
})->with([
    'bare slug' => ['united-kingdom'],
    'prefixed slug' => ['en:united-kingdom'],
    'mixed case' => ['United-Kingdom'],
]);

it('does not match a country the product is not sold in', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Milk","countries_tags":["en:france"]}');

    expect($product?->isFromCountry('united-kingdom'))->toBeFalse();
});

it('matches a category filter the same way', function (): void {
    $product = OpenFoodFactsProduct::fromJsonLine('{"code":"1","product_name":"Cola","categories_tags":["en:beverages","en:colas"]}');

    expect($product?->isInCategory('colas'))->toBeTrue();
    expect($product?->isInCategory('dairies'))->toBeFalse();
});
