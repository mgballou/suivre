<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use Illuminate\Database\QueryException;

it('persists a synonym against its food item', function (): void {
    $foodItem = FoodItem::factory()->named('Eggplant')->createQuietly();

    $alias = FoodItemAlias::factory()->for($foodItem)->named('Aubergine')->createQuietly();

    expect($alias->load('foodItem')->foodItem->id)->toBe($foodItem->id);
    expect($foodItem->load('aliases')->aliases->pluck('alias')->all())->toBe(['Aubergine']);
});

it('derives the normalized alias through the catalog normalization rule', function (): void {
    $alias = FoodItemAlias::factory()->named('Soft-Drink  (Fizzy)')->createQuietly();

    expect($alias->fresh()->normalized_alias)->toBe('soft drink fizzy');
});

it('rejects the same normalized synonym twice on one food item', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    FoodItemAlias::factory()->for($foodItem)->named('Aubergine')->createQuietly();

    expect(fn () => FoodItemAlias::factory()->for($foodItem)->named('AUBERGINE!')->createQuietly())
        ->toThrow(QueryException::class);
});

it('allows two food items to share a synonym', function (): void {
    FoodItemAlias::factory()->named('Soda')->createQuietly();

    $second = FoodItemAlias::factory()->named('Soda')->createQuietly();

    expect($second->exists)->toBeTrue();
});

it('is removed with the food item it belongs to', function (): void {
    $foodItem = FoodItem::factory()->withAliases(['Aubergine'])->createQuietly();

    $foodItem->delete();

    expect(FoodItemAlias::query()->where('food_item_id', $foodItem->id)->exists())->toBeFalse();
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new FoodItemAlias())->getMorphClass())->toBe('food_item_aliases');
});
