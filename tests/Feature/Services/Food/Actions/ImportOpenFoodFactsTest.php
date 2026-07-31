<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Actions;

use App\Enums\FoodItemType;
use App\Exceptions\Food\CatalogSourceUnreadableException;
use App\Models\Category;
use App\Models\FoodItem;
use App\Services\Food\Actions\ImportOpenFoodFacts;
use App\Services\Food\Data\CatalogImportFilters;
use Database\Seeders\CategoryTaxonomySeeder;

beforeEach(function (): void {
    $this->seed(CategoryTaxonomySeeder::class);
});

function fixture(): string
{
    return base_path('tests/Fixtures/open-food-facts-sample.jsonl');
}

/**
 * @return array<int, string>
 */
function slugsFor(string $name): array
{
    $foodItem = FoodItem::query()
        ->where('normalized_name', FoodItem::normalizeName($name))
        ->firstOrFail();

    /** @var array<int, string> $slugs */
    $slugs = $foodItem->categories()->orderBy('slug')->pluck('slug')->all();

    return $slugs;
}

it('creates a catalog entry for every usable product in the export', function (): void {
    $summary = app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect($summary->created)->toBe(13);
    expect(FoodItem::query()->count())->toBe(13);
});

it('skips lines that cannot become a catalog entry', function (): void {
    // A blank name, a missing barcode, a name that normalizes away, and a line
    // of broken JSON — all ordinary in a crowd-sourced dump.
    $summary = app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect($summary->skipped)->toBe(4);
});

it('stamps imported rows with their provenance', function (): void {
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    $foodItem = FoodItem::query()
        ->where('normalized_name', 'sliced wholemeal bread')
        ->firstOrFail();

    expect($foodItem->source)->toBe(config()->string('food.catalog.source'));
    expect($foodItem->source_ref)->toBe('5000000000002');
    expect($foodItem->isImported())->toBeTrue();
});

it('creates only the food item type an import is allowed to create', function (): void {
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    $types = FoodItem::query()->pluck('type')->unique();

    expect($types->all())->toBe([FoodItemType::Item]);
    expect(FoodItemType::Item->isImportable())->toBeTrue();
});

it('derives the categories the export gives real signal for', function (string $name, array $slugs): void {
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect(slugsFor($name))->toBe($slugs);
})->with([
    'allergen from the allergen list' => ['Sliced wholemeal bread', ['gluten']],
    'peanuts count as nuts' => ['Crunchy peanut butter', ['added-sugar', 'nuts']],
    'soy' => ['Firm tofu', ['soy']],
    'egg' => ['Mayonnaise', ['egg']],
    'caffeine from the product category' => ['Ground coffee', ['caffeine']],
    'two ingredient triggers at once' => ['Cola', ['added-sugar', 'caffeine']],
    'allergen and category trigger together' => ['Pale ale', ['alcohol', 'gluten']],
]);

it('leaves a product the export says nothing useful about untagged', function (string $name): void {
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect(slugsFor($name))->toBe([]);
})->with([
    'no allergen or ingredient signal' => ['Sea salt flakes'],
    'a research category the export cannot know' => ['Tomato passata'],
    'traces are a warning, not an exposure' => ['Fruit and oat bar'],
]);

it('folds a second barcode for the same food into one catalog entry', function (): void {
    $summary = app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect($summary->merged)->toBe(1);
    expect(FoodItem::query()->where('normalized_name', 'whole milk')->count())->toBe(1);
});

it('accumulates the categories every barcode for a food knew', function (): void {
    // The first record carries only milk; the second adds sugar. The catalog
    // entry ends up with both.
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect(slugsFor('Whole milk'))->toBe(['added-sugar', 'dairy']);
});

it('keeps the display name of the record that created the row', function (): void {
    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    $foodItem = FoodItem::query()->where('normalized_name', 'whole milk')->firstOrFail();

    expect($foodItem->name)->toBe('Whole milk');
    expect($foodItem->source_ref)->toBe('3017620422003');
});

it('creates nothing new on a second run over the same export', function (): void {
    $import = app(ImportOpenFoodFacts::class);

    $first = $import(fixture(), new CatalogImportFilters());
    $second = $import(fixture(), new CatalogImportFilters());

    expect($second->created)->toBe(0);
    expect($second->merged)->toBe($first->created + $first->merged);
    expect(FoodItem::query()->count())->toBe($first->created);
});

it('does not duplicate the category links on a re-run', function (): void {
    $import = app(ImportOpenFoodFacts::class);

    $import(fixture(), new CatalogImportFilters());
    $import(fixture(), new CatalogImportFilters());

    expect(slugsFor('Cola'))->toBe(['added-sugar', 'caffeine']);
});

it('never strips a category an operator curated by hand', function (): void {
    $histamine = Category::query()->where('slug', 'histamine')->firstOrFail();

    FoodItem::factory()->named('Pale ale')->withCategories([$histamine])->createQuietly();

    app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters());

    expect(slugsFor('Pale ale'))->toBe(['alcohol', 'gluten', 'histamine']);
});

it('imports only products sold in the country asked for', function (): void {
    $summary = app(ImportOpenFoodFacts::class)(
        fixture(),
        new CatalogImportFilters(country: 'united-kingdom'),
    );

    expect($summary->created)->toBe(12);
    expect($summary->merged)->toBe(0);
    expect(FoodItem::query()->where('normalized_name', 'chorizo')->exists())->toBeFalse();
});

it('imports only products in the category asked for', function (): void {
    $summary = app(ImportOpenFoodFacts::class)(
        fixture(),
        new CatalogImportFilters(category: 'beverages'),
    );

    expect($summary->created)->toBe(2);
    expect(slugsFor('Ground coffee'))->toBe(['caffeine']);
});

it('counts the limit in products imported, not lines read', function (): void {
    // The French record on line two is filtered out and must not consume any of
    // the quota, so reaching three imports takes four lines.
    $summary = app(ImportOpenFoodFacts::class)(
        fixture(),
        new CatalogImportFilters(country: 'united-kingdom', limit: 3),
    );

    expect($summary->created)->toBe(3);
    expect(FoodItem::query()->count())->toBe(3);
});

it('resumes part way through the export', function (): void {
    $summary = app(ImportOpenFoodFacts::class)(fixture(), new CatalogImportFilters(skip: 2));

    expect(FoodItem::query()->where('normalized_name', 'whole milk')->exists())->toBeFalse();
    expect($summary->created)->toBe(12);
});

it('overlapping a resume merges rather than duplicating', function (): void {
    $import = app(ImportOpenFoodFacts::class);

    $import(fixture(), new CatalogImportFilters(limit: 5));
    $import(fixture(), new CatalogImportFilters(skip: 2));

    expect(FoodItem::query()->count())->toBe(13);
});

it('reports each imported product to the progress callback', function (): void {
    $seen = 0;

    $summary = app(ImportOpenFoodFacts::class)(
        fixture(),
        new CatalogImportFilters(),
        function () use (&$seen): void {
            $seen++;
        },
    );

    expect($seen)->toBe($summary->imported());
});

it('reads a gzipped export', function (): void {
    $path = sys_get_temp_dir() . '/off-sample-' . uniqid() . '.jsonl.gz';

    file_put_contents($path, gzencode((string) file_get_contents(fixture())));

    $summary = app(ImportOpenFoodFacts::class)($path, new CatalogImportFilters());

    unlink($path);

    expect($summary->created)->toBe(13);
});

it('fails loudly when the export cannot be read', function (): void {
    app(ImportOpenFoodFacts::class)('/nowhere/open-food-facts.jsonl', new CatalogImportFilters());
})->throws(CatalogSourceUnreadableException::class);
