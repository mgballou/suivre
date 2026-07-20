<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\FoodItemKind;
use App\Models\Category;
use App\Models\FoodItem;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('persists a food item', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();

    expect($foodItem->exists)->toBeTrue();
    $this->assertDatabaseHas('food_items', [
        'id' => $foodItem->id,
        'name' => $foodItem->name,
    ]);
});

it('is global — the table carries no owning user', function (): void {
    expect(Schema::hasColumn('food_items', 'user_id'))->toBeFalse();
});

it('casts kind to the FoodItemKind enum', function (): void {
    $foodItem = FoodItem::factory()->dish()->createQuietly();

    expect($foodItem->fresh()->kind)->toBe(FoodItemKind::Dish);
});

it('defaults to an atomic item', function (): void {
    expect(FoodItem::factory()->createQuietly()->kind)->toBe(FoodItemKind::Item);
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new FoodItem())->getMorphClass())->toBe('food_items');
});

it('derives the normalized name from the display name', function (): void {
    $foodItem = FoodItem::factory()->named('Crème Brûlée, Vanilla!')->createQuietly();

    expect($foodItem->fresh()->normalized_name)->toBe('creme brulee vanilla');
});

it('re-derives the normalized name when the display name changes', function (): void {
    $foodItem = FoodItem::factory()->named('Eggplant')->createQuietly();

    $foodItem->update(['name' => 'Aubergine (large)']);

    expect($foodItem->fresh()->normalized_name)->toBe('aubergine large');
});

it('refuses to mass-assign the normalized name, which is derived not entered', function (): void {
    expect(fn () => FoodItem::query()->create([
        'name' => 'Greek Yoghurt',
        'normalized_name' => 'something else entirely',
    ]))->toThrow(MassAssignmentException::class);
});

it('normalizes accents, case, punctuation and whitespace uniformly', function (string $input, string $expected): void {
    expect(FoodItem::normalizeName($input))->toBe($expected);
})->with([
    ['Café Latte', 'cafe latte'],
    ['  SPAGHETTI   bolognese  ', 'spaghetti bolognese'],
    ["Ben & Jerry's", 'ben jerry s'],
    ['Coca-Cola Zero 330ml', 'coca cola zero 330ml'],
    ['jalapeño', 'jalapeno'],
    ['---', ''],
]);

it('distinguishes an imported row from a curated one', function (): void {
    $imported = FoodItem::factory()->imported(sourceRef: '3017620422003')->createQuietly();
    $curated = FoodItem::factory()->createQuietly();

    expect($imported->isImported())->toBeTrue();
    expect($imported->isCurated())->toBeFalse();
    expect($curated->isCurated())->toBeTrue();
    expect($curated->isImported())->toBeFalse();
});

it('rejects two imports of the same dataset record', function (): void {
    FoodItem::factory()->imported(sourceRef: '3017620422003')->createQuietly();

    expect(fn () => FoodItem::factory()->imported(sourceRef: '3017620422003')->createQuietly())
        ->toThrow(QueryException::class);
});

it('allows many curated rows to share an absent provenance', function (): void {
    FoodItem::factory()->count(3)->createQuietly();

    expect(FoodItem::query()->whereNull('source')->count())->toBe(3);
});

it('attaches and detaches curated trigger categories', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();
    $dairy = Category::factory()->createQuietly();
    $gluten = Category::factory()->createQuietly();

    $foodItem->categories()->attach([$dairy->id, $gluten->id]);

    expect($foodItem->load('categories')->categories->pluck('id')->all())
        ->toEqualCanonicalizing([$dairy->id, $gluten->id]);

    $foodItem->categories()->detach($gluten->id);

    expect($foodItem->load('categories')->categories->pluck('id')->all())->toBe([$dairy->id]);
});

it('is reachable from the category side of the pivot', function (): void {
    $category = Category::factory()->createQuietly();
    $foodItem = FoodItem::factory()->withCategories([$category])->createQuietly();

    expect($category->load('foodItems')->foodItems->pluck('id')->all())->toBe([$foodItem->id]);
});

it('rejects the same category twice on one food item', function (): void {
    $foodItem = FoodItem::factory()->createQuietly();
    $category = Category::factory()->createQuietly();

    $foodItem->categories()->attach($category->id);

    expect(fn () => $foodItem->categories()->attach($category->id))
        ->toThrow(QueryException::class);
});

it('builds a composite dish from catalog components', function (): void {
    $rice = FoodItem::factory()->named('Basmati rice')->createQuietly();
    $cream = FoodItem::factory()->named('Double cream')->createQuietly();

    $korma = FoodItem::factory()->named('Chicken korma')->composedOf([$rice, $cream])->createQuietly();

    expect($korma->kind->isComposite())->toBeTrue();
    expect($korma->load('components')->components->pluck('id')->all())
        ->toEqualCanonicalizing([$rice->id, $cream->id]);
    expect($cream->load('dishes')->dishes->pluck('id')->all())->toBe([$korma->id]);
});

it('refuses to make a dish a component of itself', function (): void {
    $dish = FoodItem::factory()->dish()->createQuietly();

    expect(fn () => $dish->components()->attach($dish->id))->toThrow(QueryException::class);
});
