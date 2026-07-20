<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

it('persists a free-text entry awaiting classification', function (): void {
    $meal = Meal::factory()->createQuietly();

    $entry = FoodEntry::factory()->forMeal($meal)->pendingClassification()->createQuietly();

    expect($entry->food_item_id)->toBeNull();
    expect($entry->text)->not->toBeNull();
    expect($entry->isPendingClassification())->toBeTrue();
    expect($entry->isClassified())->toBeFalse();
});

it('persists an entry resolved to a catalog food item', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    $entry = FoodEntry::factory()->resolvedToFoodItem($foodItem)->createQuietly();

    $fresh = $entry->fresh();

    expect($fresh->food_item_id)->toBe($foodItem->id);
    expect($fresh->text)->toBeNull();
    expect($fresh->isClassified())->toBeTrue();
    expect($fresh->isPendingClassification())->toBeFalse();
});

it('keeps the raw text alongside a resolved food item when both are given', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    $entry = FoodEntry::factory()->resolvedToFoodItem($foodItem, 'two poached eggs')->createQuietly();

    expect($entry->food_item_id)->toBe($foodItem->id);
    expect($entry->text)->toBe('two poached eggs');
});

it('belongs to the catalog food item it was resolved to', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    $entry = FoodEntry::factory()->resolvedToFoodItem($foodItem)->createQuietly();

    expect($entry->load('foodItem')->foodItem?->id)->toBe($foodItem->id);
});

it('has no food item while it awaits classification', function (): void {
    $entry = FoodEntry::factory()->pendingClassification()->createQuietly();

    expect($entry->load('foodItem')->foodItem)->toBeNull();
});

it('rejects a food item that is not in the catalog', function (): void {
    $meal = Meal::factory()->createQuietly();

    expect(fn () => FoodEntry::query()->create([
        'meal_id' => $meal->id,
        'food_item_id' => 999_999_999,
        'text' => 'ghost food',
    ]))->toThrow(QueryException::class);
});

it('refuses to delete a catalog food item that entries still point at', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    FoodEntry::factory()->resolvedToFoodItem($foodItem)->createQuietly();

    expect(fn () => $foodItem->delete())->toThrow(QueryException::class);
});

it('rejects an entry carrying neither text nor a food item', function (): void {
    $meal = Meal::factory()->createQuietly();

    expect(fn () => FoodEntry::query()->create([
        'meal_id' => $meal->id,
        'food_item_id' => null,
        'text' => null,
    ]))->toThrow(QueryException::class);
});

it('belongs to its meal', function (): void {
    $meal = Meal::factory()->createQuietly();

    $entry = FoodEntry::factory()->forMeal($meal)->createQuietly();

    expect($entry->load('meal')->meal->id)->toBe($meal->id);
});

it('inherits the eaten_at instant from its loaded meal', function (): void {
    $meal = Meal::factory()
        ->eatenAt(CarbonImmutable::parse('2026-07-06 19:30:00'))
        ->createQuietly();
    $entry = FoodEntry::factory()->forMeal($meal)->createQuietly();

    expect($entry->load('meal')->eaten_at?->toDateTimeString())->toBe('2026-07-06 19:30:00');
});

it('yields a null eaten_at when the parent meal is not loaded', function (): void {
    $entry = FoodEntry::factory()->createQuietly();

    expect($entry->fresh()->eaten_at)->toBeNull();
});

it('is backfilled with its parent meal when iterating a meal collection', function (): void {
    $meal = Meal::factory()
        ->eatenAt(CarbonImmutable::parse('2026-07-06 19:30:00'))
        ->createQuietly();
    FoodEntry::factory()->count(2)->forMeal($meal)->createQuietly();

    $meal->load('entries');
    $meal->entries->each(fn (FoodEntry $entry) => $entry->setRelation('meal', $meal));

    expect($meal->entries->every(
        fn (FoodEntry $entry) => $entry->eaten_at?->toDateTimeString() === '2026-07-06 19:30:00'
    ))->toBeTrue();
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new FoodEntry())->getMorphClass())->toBe('food_entries');
});
