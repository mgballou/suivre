<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\ReviewItems;

use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewItems\Actions\CatalogReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\DismissReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\ResolveReviewItemAction;
use App\Filament\Resources\ReviewItems\Pages\ListReviewItems;
use App\Filament\Resources\ReviewItems\Pages\ViewReviewItem;
use App\Filament\Resources\ReviewItems\ReviewItemResource;
use App\Models\Category;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\ReviewItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());
});

function waiting(string $text = 'aunt bettys slice'): ReviewItem
{
    $entry = FoodEntry::factory()->pendingClassification()->createQuietly(['text' => $text]);

    return ReviewItem::factory()->reviewable($entry)->createQuietly(['text' => $text]);
}

it('lists the entries waiting on a decision', function (): void {
    $items = ReviewItem::factory()->count(3)->createQuietly();

    Livewire::test(ListReviewItems::class)
        ->assertOk()
        ->assertCanSeeTableRecords($items);
});

it('offers all three decisions on a waiting entry', function (): void {
    $item = waiting();

    Livewire::test(ListReviewItems::class)
        ->assertActionExists(TestAction::make(ResolveReviewItemAction::class)->table($item))
        ->assertActionExists(TestAction::make(CatalogReviewItemAction::class)->table($item))
        ->assertActionExists(TestAction::make(DismissReviewItemAction::class)->table($item));
});

it('leaves decided entries out of the queue by default', function (): void {
    // A work queue, not a log: the default filter is the open statuses, so a
    // decided item is only ever reached on purpose.
    $waiting = waiting();
    $decided = ReviewItem::factory()->resolved()->createQuietly();

    Livewire::test(ListReviewItems::class)
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$decided]);
});

it('hides the decisions on an entry someone already decided', function (): void {
    $item = ReviewItem::factory()->resolved()->createQuietly();

    Livewire::test(ViewReviewItem::class, ['record' => $item->getKey()])
        ->assertActionHidden(ResolveReviewItemAction::class)
        ->assertActionHidden(CatalogReviewItemAction::class)
        ->assertActionHidden(DismissReviewItemAction::class);
});

it('offers the decisions on the view page of a waiting entry', function (): void {
    $item = waiting();

    Livewire::test(ViewReviewItem::class, ['record' => $item->getKey()])
        ->assertOk()
        ->assertActionVisible(ResolveReviewItemAction::class)
        ->assertActionVisible(DismissReviewItemAction::class);
});

it('links an entry to a catalog food and grows the synonym list', function (): void {
    $item = waiting();
    $bread = FoodItem::factory()->named('Sourdough bread')->createQuietly();

    Livewire::test(ListReviewItems::class)
        ->callAction(
            TestAction::make(ResolveReviewItemAction::class)->table($item),
            ['food_item_id' => $bread->id],
        )
        ->assertHasNoActionErrors();

    expect($item->fresh()?->status)->toBe(ReviewStatus::Resolved);
    expect($item->reviewable->fresh()?->food_item_id)->toBe($bread->id);
    expect(FoodItemAlias::query()->where('food_item_id', $bread->id)->count())->toBe(1);
});

it('adds a new catalog food with the categories the operator picked', function (): void {
    $item = waiting('mature chedar');
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy', 'slug' => 'dairy']);

    Livewire::test(ListReviewItems::class)
        ->callAction(
            TestAction::make(CatalogReviewItemAction::class)->table($item),
            ['name' => 'Mature cheddar', 'categories' => [$dairy->id]],
        )
        ->assertHasNoActionErrors();

    $foodItem = FoodItem::query()->where('normalized_name', 'mature cheddar')->firstOrFail();

    expect($foodItem->categories()->pluck('slug')->all())->toBe(['dairy']);
    expect($item->fresh()?->status)->toBe(ReviewStatus::Resolved);
});

it('requires a name before a food reaches the catalog', function (): void {
    Livewire::test(ListReviewItems::class)
        ->callAction(
            TestAction::make(CatalogReviewItemAction::class)->table(waiting()),
            ['name' => ''],
        )
        ->assertHasActionErrors(['name' => ['required']]);
});

it('dismisses an entry without touching the catalog', function (): void {
    $item = waiting();

    Livewire::test(ListReviewItems::class)
        ->callAction(TestAction::make(DismissReviewItemAction::class)->table($item));

    expect($item->fresh()?->status)->toBe(ReviewStatus::Dismissed);
    expect($item->reviewable->fresh()?->food_item_id)->toBeNull();
    expect(FoodItem::query()->count())->toBe(0);
});

it('counts what is waiting on the navigation item', function (): void {
    ReviewItem::factory()->count(2)->createQuietly();
    ReviewItem::factory()->resolved()->createQuietly();

    expect(ReviewItemResource::getNavigationBadge())->toBe('2');
});

it('shows no badge when the queue is clear', function (): void {
    ReviewItem::factory()->dismissed()->createQuietly();

    expect(ReviewItemResource::getNavigationBadge())->toBeNull();
});

it('keeps the queue away from members', function (): void {
    $this->actingAs(User::factory()->createQuietly());

    $this->get(ReviewItemResource::getUrl('index'))->assertForbidden();
});
