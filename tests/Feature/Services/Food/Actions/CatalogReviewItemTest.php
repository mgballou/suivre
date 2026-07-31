<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Actions;

use App\Enums\FoodItemType;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\ReviewItem;
use App\Services\Food\Actions\CatalogReviewItem;
use App\Services\Food\Actions\ClassifyFoodEntry;
use Throwable;

function pending(string $text): ReviewItem
{
    $entry = FoodEntry::factory()->pendingClassification()->createQuietly(['text' => $text]);

    return ReviewItem::factory()->reviewable($entry)->createQuietly(['text' => $text]);
}

it('creates a curated catalog entry carrying the categories chosen', function (): void {
    $histamine = Category::factory()->createQuietly(['name' => 'Histamine', 'slug' => 'histamine']);
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy', 'slug' => 'dairy']);

    $foodItem = app(CatalogReviewItem::class)(
        pending('mature cheddar'),
        'Mature cheddar',
        [$histamine->id, $dairy->id],
    );

    expect($foodItem->name)->toBe('Mature cheddar');
    expect($foodItem->type)->toBe(FoodItemType::Item);
    expect($foodItem->categories()->orderBy('slug')->pluck('slug')->all())->toBe(['dairy', 'histamine']);
});

it('marks the new food as curated, not imported', function (): void {
    // Provenance matters: a later catalog import must not claim a row a person
    // decided on, and `isImported()` is what tells the two apart.
    $foodItem = app(CatalogReviewItem::class)(pending('mature cheddar'), 'Mature cheddar');

    expect($foodItem->source)->toBeNull();
    expect($foodItem->isCurated())->toBeTrue();
});

it('resolves the queue item against the food it just created', function (): void {
    $item = pending('mature cheddar');

    $foodItem = app(CatalogReviewItem::class)($item, 'Mature cheddar');

    expect($item->fresh()?->status)->toBe(ReviewStatus::Resolved);
    expect($item->reviewable->fresh()?->food_item_id)->toBe($foodItem->id);
});

it('keeps the queued text matchable when the operator tidies the name', function (): void {
    app(CatalogReviewItem::class)(pending('mature chedar'), 'Mature cheddar');

    $result = app(ClassifyFoodEntry::class)('mature chedar');

    expect($result->outcome->isMatched())->toBeTrue();
    expect($result->foodItem?->name)->toBe('Mature cheddar');
});

it('accepts a food that carries no trigger category at all', function (): void {
    $foodItem = app(CatalogReviewItem::class)(pending('sea salt'), 'Sea salt');

    expect($foodItem->categories()->count())->toBe(0);
});

it('creates nothing when the item was already decided', function (): void {
    $item = pending('mature cheddar');

    app(CatalogReviewItem::class)($item, 'Mature cheddar');

    try {
        app(CatalogReviewItem::class)($item, 'Something else');
    } catch (Throwable) {
        // The rollback is the assertion below.
    }

    expect(FoodItem::query()->where('normalized_name', 'something else')->exists())->toBeFalse();
});
