<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Actions;

use App\Enums\FoodClassificationOutcome;
use App\Models\Category;
use App\Models\FoodItem;
use App\Services\Food\Actions\ClassifyFoodEntry;

it('resolves an exact match to its food item and categories', function (): void {
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
    FoodItem::factory()->named('Cheddar cheese')->withCategories([$dairy])->createQuietly();

    $result = app(ClassifyFoodEntry::class)('Cheddar cheese');

    expect($result->outcome)->toBe(FoodClassificationOutcome::Matched);
    expect($result->foodItem?->name)->toBe('Cheddar cheese');
    expect($result->categories->pluck('id')->all())->toBe([$dairy->id]);
    expect($result->score)->toBe(1.0);
});

it('matches a typo within the similarity threshold', function (): void {
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
    $cheddar = FoodItem::factory()->named('Cheddar cheese')->withCategories([$dairy])->createQuietly();

    $result = app(ClassifyFoodEntry::class)('chedar cheese');

    expect($result->outcome)->toBe(FoodClassificationOutcome::Matched);
    expect($result->foodItem?->id)->toBe($cheddar->id);
    expect($result->score)->toBeGreaterThan(0.0)->toBeLessThan(1.0);
});

it('resolves an alias to its parent food item', function (): void {
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
    $cheddar = FoodItem::factory()
        ->named('Cheddar cheese')
        ->withCategories([$dairy])
        ->withAliases(['Aged cheddar'])
        ->createQuietly();

    $result = app(ClassifyFoodEntry::class)('aged cheddar');

    expect($result->outcome)->toBe(FoodClassificationOutcome::Matched);
    expect($result->foodItem?->id)->toBe($cheddar->id);
    expect($result->categories->pluck('id')->all())->toBe([$dairy->id]);
});

it('reports low confidence for gibberish with no close catalog match', function (): void {
    Category::factory()->createQuietly(['name' => 'Dairy']);
    FoodItem::factory()->named('Cheddar cheese')->createQuietly();

    $result = app(ClassifyFoodEntry::class)('zzqxw plonk frobnicate');

    expect($result->outcome)->toBe(FoodClassificationOutcome::LowConfidence);
    expect($result->foodItem)->toBeNull();
    expect($result->categories)->toBeEmpty();
    expect($result->score)->toBe(0.0);
});

it('reports low confidence for blank text without querying the catalog', function (): void {
    $result = app(ClassifyFoodEntry::class)('   ');

    expect($result->outcome)->toBe(FoodClassificationOutcome::LowConfidence);
    expect($result->foodItem)->toBeNull();
});

it('is deterministic: the same input yields the same match and score every time', function (): void {
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
    FoodItem::factory()->named('Cheddar cheese')->withCategories([$dairy])->createQuietly();

    $action = app(ClassifyFoodEntry::class);

    $first = $action('chedar cheese');
    $second = $action('chedar cheese');

    expect($second->outcome)->toBe($first->outcome);
    expect($second->foodItem?->id)->toBe($first->foodItem?->id);
    expect($second->score)->toBe($first->score);
    expect($second->categories->pluck('id')->all())->toBe($first->categories->pluck('id')->all());
});

it('breaks ties deterministically when two candidates score identically', function (): void {
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
    $first = FoodItem::factory()->named('Cheddar cheese')->withCategories([$dairy])->createQuietly();
    FoodItem::factory()->named('Cheddar cheese')->withCategories([$dairy])->createQuietly();

    $result = app(ClassifyFoodEntry::class)('Cheddar cheese');

    expect($result->outcome)->toBe(FoodClassificationOutcome::Matched);
    expect($result->score)->toBe(1.0);
    expect($result->foodItem?->id)->toBe($first->id);
});
