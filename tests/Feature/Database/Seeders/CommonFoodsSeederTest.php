<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use App\Enums\CategoryGroup;
use App\Models\Category;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Services\Food\Actions\ClassifyFoodEntry;
use Database\Seeders\CategoryTaxonomySeeder;
use Database\Seeders\CommonFoodsSeeder;

beforeEach(function (): void {
    $this->seed(CategoryTaxonomySeeder::class);
    $this->seed(CommonFoodsSeeder::class);
});

/**
 * @return array<int, string>
 */
function tagsOf(string $name): array
{
    /** @var array<int, string> $slugs */
    $slugs = FoodItem::query()
        ->where('normalized_name', FoodItem::normalizeName($name))
        ->firstOrFail()
        ->categories()
        ->orderBy('slug')
        ->pluck('slug')
        ->all();

    return $slugs;
}

it('fills the catalog with everyday foods', function (): void {
    expect(FoodItem::query()->count())->toBeGreaterThan(100);
});

it('carries the research categories no dataset can supply', function (string $name, string $slug): void {
    expect(tagsOf($name))->toContain($slug);
})->with([
    'nightshade' => ['Tomato', 'nightshade'],
    'nightshade in a staple' => ['Potato', 'nightshade'],
    'fodmap' => ['Onion', 'fodmap'],
    'fodmap in a fruit' => ['Apple', 'fodmap'],
    'histamine' => ['Red wine', 'histamine'],
    'histamine in a cheese' => ['Blue cheese', 'histamine'],
]);

it('tags a food with everything it carries at once', function (): void {
    expect(tagsOf('Beer'))->toBe(['alcohol', 'gluten', 'histamine']);
});

it('matches the plain word a person actually types', function (string $typed, string $expected): void {
    $result = app(ClassifyFoodEntry::class)($typed);

    expect($result->outcome->isMatched())->toBeTrue();
    expect($result->foodItem?->name)->toBe($expected);
})->with([
    'a bare staple' => ['eggs', 'Eggs'],
    'a synonym' => ['eggplant', 'Aubergine'],
    'the other side of the atlantic' => ['zucchini', 'Courgette'],
    'a drink' => ['coffee', 'Coffee'],
    'a brand-free noun' => ['bread', 'Bread'],
]);

it('resolves a synonym to the tags of the food it names', function (): void {
    $result = app(ClassifyFoodEntry::class)('cilantro');

    expect($result->foodItem?->name)->toBe('Coriander');
});

it('registers synonyms without duplicating the canonical name', function (): void {
    $aubergine = FoodItem::query()->where('normalized_name', 'aubergine')->firstOrFail();

    expect(FoodItemAlias::query()->where('food_item_id', $aubergine->id)->pluck('alias')->all())
        ->toBe(['eggplant']);
});

it('only ever assigns categories that exist in the taxonomy', function (): void {
    $slugs = Category::query()->pluck('slug');

    $orphans = FoodItem::query()
        ->with('categories')
        ->get()
        ->flatMap(fn (FoodItem $food): array => $food->categories->pluck('slug')->all())
        ->unique()
        ->reject(fn (string $slug): bool => $slugs->contains($slug));

    expect($orphans->all())->toBe([]);
});

it('uses every auto-derivable and research group', function (): void {
    $groups = Category::query()
        ->has('foodItems')
        ->pluck('group')
        ->unique()
        ->map(fn (CategoryGroup $group): string => $group->value)
        ->sort()
        ->values();

    expect($groups->all())->toBe(['allergen', 'ingredient', 'research']);
});

it('creates the foods as curated, so an import cannot claim them', function (): void {
    expect(FoodItem::query()->whereNotNull('source')->count())->toBe(0);
});

it('adds nothing on a second run', function (): void {
    $foods = FoodItem::query()->count();
    $aliases = FoodItemAlias::query()->count();

    $this->seed(CommonFoodsSeeder::class);

    expect(FoodItem::query()->count())->toBe($foods);
    expect(FoodItemAlias::query()->count())->toBe($aliases);
});
