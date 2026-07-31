<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Food\Actions;

use App\Enums\ReviewStatus;
use App\Exceptions\Food\ReviewItemAlreadyDecidedException;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\ReviewItem;
use App\Services\Food\Actions\ClassifyFoodEntry;
use App\Services\Food\Actions\ResolveReviewItem;

function queued(string $text, ?FoodEntry $entry = null): ReviewItem
{
    $entry ??= FoodEntry::factory()->pendingClassification()->createQuietly(['text' => $text]);

    return ReviewItem::factory()->reviewable($entry)->createQuietly(['text' => $text]);
}

it('links the queued entry to the food it was resolved against', function (): void {
    $entry = FoodEntry::factory()->pendingClassification()->createQuietly(['text' => 'aunt bettys slice']);
    $item = queued('aunt bettys slice', $entry);
    $bread = FoodItem::factory()->named('Sourdough bread')->createQuietly();

    app(ResolveReviewItem::class)($item, $bread);

    expect($entry->fresh()?->food_item_id)->toBe($bread->id);
});

it('records the queued text as an alias so the classifier learns it', function (): void {
    $item = queued('aunt bettys slice');
    $bread = FoodItem::factory()->named('Sourdough bread')->createQuietly();

    app(ResolveReviewItem::class)($item, $bread);

    expect(FoodItemAlias::query()->where('food_item_id', $bread->id)->pluck('alias')->all())
        ->toBe(['aunt bettys slice']);
});

it('makes the same text match on its own next time', function (): void {
    // The point of the whole queue: an operator answers a question once.
    $item = queued('aunt bettys slice');
    $bread = FoodItem::factory()->named('Sourdough bread')->createQuietly();

    expect(app(ClassifyFoodEntry::class)('aunt bettys slice')->outcome->isMatched())->toBeFalse();

    app(ResolveReviewItem::class)($item, $bread);

    $result = app(ClassifyFoodEntry::class)('aunt bettys slice');

    expect($result->outcome->isMatched())->toBeTrue();
    expect($result->foodItem?->id)->toBe($bread->id);
});

it('closes the item as resolved', function (): void {
    $item = queued('aunt bettys slice');

    app(ResolveReviewItem::class)($item, FoodItem::factory()->named('Sourdough bread')->createQuietly());

    expect($item->fresh()?->status)->toBe(ReviewStatus::Resolved);
    expect($item->fresh()?->isOpen())->toBeFalse();
});

it('adds no alias when the text already normalizes to the food name', function (): void {
    $item = queued('Sourdough Bread!');
    $bread = FoodItem::factory()->named('sourdough bread')->createQuietly();

    app(ResolveReviewItem::class)($item, $bread);

    expect(FoodItemAlias::query()->where('food_item_id', $bread->id)->count())->toBe(0);
});

it('does not add a second alias when one already says the same thing', function (): void {
    $bread = FoodItem::factory()->named('Sourdough bread')->withAliases(['aunt bettys slice'])->createQuietly();

    app(ResolveReviewItem::class)(queued('aunt bettys slice'), $bread);

    expect(FoodItemAlias::query()->where('food_item_id', $bread->id)->count())->toBe(1);
});

it('refuses to decide an item that is already closed', function (): void {
    $item = queued('aunt bettys slice');
    $bread = FoodItem::factory()->named('Sourdough bread')->createQuietly();

    app(ResolveReviewItem::class)($item, $bread);
    app(ResolveReviewItem::class)($item, $bread);
})->throws(ReviewItemAlreadyDecidedException::class);
