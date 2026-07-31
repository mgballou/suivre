<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Meals\Actions;

use App\Enums\MealType;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Meals\Actions\LogMeal;
use App\Services\Meals\Data\MealEntryDraft;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogMealTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_meal_with_its_confirmed_catalog_matches(): void
    {
        $user = User::factory()->tracking()->create();
        $cheddar = FoodItem::factory()->createQuietly(['name' => 'cheddar cheese']);

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Lunch,
            entries: [new MealEntryDraft(text: 'cheddar', foodItemId: $cheddar->id)],
        );

        $this->assertSame($user->id, $meal->user_id);
        $this->assertTrue($meal->meal_type->isLunch());

        $entry = $meal->entries()->sole();

        $this->assertSame($cheddar->id, $entry->food_item_id);
        $this->assertSame('cheddar', $entry->text);
    }

    public function test_it_keeps_the_typed_text_alongside_the_match(): void
    {
        // The raw text is what the review queue and an operator inspecting a
        // classification need; discarding it on a match would lose that.
        $user = User::factory()->tracking()->create();
        $item = FoodItem::factory()->createQuietly(['name' => 'wholemeal bread']);

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Breakfast,
            entries: [new MealEntryDraft(text: 'brown toast', foodItemId: $item->id)],
        );

        $this->assertSame('brown toast', $meal->entries()->sole()->text);
    }

    public function test_an_unresolved_line_still_saves_and_is_queued_for_review(): void
    {
        // A classification miss must never block logging (D9).
        $user = User::factory()->tracking()->create();

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Dinner,
            entries: [new MealEntryDraft(text: 'grandmas mystery stew', foodItemId: null)],
        );

        $entry = $meal->entries()->sole();

        $this->assertNull($entry->food_item_id);
        $this->assertTrue($entry->isPendingClassification());

        $review = ReviewItem::query()->sole();

        $this->assertSame($entry->getMorphClass(), $review->reviewable_type);
        $this->assertSame($entry->id, $review->reviewable_id);
        $this->assertSame('grandmas mystery stew', $review->text);
        $this->assertTrue($review->status->isPending());
        $this->assertTrue($review->isOpen());
    }

    public function test_a_confirmed_line_is_never_queued_for_review(): void
    {
        $user = User::factory()->tracking()->create();
        $item = FoodItem::factory()->createQuietly(['name' => 'porridge']);

        app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Breakfast,
            entries: [new MealEntryDraft(text: 'porridge', foodItemId: $item->id)],
        );

        $this->assertSame(0, ReviewItem::query()->count());
    }

    public function test_rejecting_a_suggestion_queues_the_line_despite_a_catalog_near_miss(): void
    {
        // The user overruled the classifier. The line is theirs, so it saves
        // unmatched — but the near miss is still worth an operator's time, and
        // the recorded score is what tells them it was close.
        $user = User::factory()->tracking()->create();
        FoodItem::factory()->createQuietly(['name' => 'cheddar cheese']);

        app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Snack,
            entries: [new MealEntryDraft(text: 'cheddar cheese', foodItemId: null)],
        );

        $review = ReviewItem::query()->sole();

        $this->assertNotNull($review->score);
        $this->assertGreaterThan(0.0, $review->score);
    }

    public function test_it_carries_the_matched_items_categories_through_to_the_entry(): void
    {
        // This is the path the correlation engine reads: it resolves tags
        // through `foodItem.categories`, never through the entry itself.
        $user = User::factory()->tracking()->create();
        $dairy = Category::factory()->createQuietly();
        $item = FoodItem::factory()->createQuietly(['name' => 'whole milk']);
        $item->categories()->attach($dairy);

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Breakfast,
            entries: [new MealEntryDraft(text: 'milk', foodItemId: $item->id)],
        );

        $entry = $meal->entries()->with('foodItem.categories')->sole();

        $this->assertNotNull($entry->foodItem);
        $this->assertTrue($entry->foodItem->categories->contains($dairy));
    }

    public function test_it_saves_every_line_of_a_multi_item_meal(): void
    {
        $user = User::factory()->tracking()->create();
        $item = FoodItem::factory()->createQuietly(['name' => 'black coffee']);

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Breakfast,
            entries: [
                new MealEntryDraft(text: 'coffee', foodItemId: $item->id),
                new MealEntryDraft(text: 'something unknown', foodItemId: null),
            ],
        );

        $this->assertSame(2, $meal->entries()->count());
        $this->assertSame(1, ReviewItem::query()->count());
    }

    public function test_a_meal_logged_for_an_earlier_day_lands_on_that_day(): void
    {
        // Back-filled meals stamp the slot's conventional hour rather than
        // midnight, so they cannot drift into the neighbouring day.
        $this->travelTo(CarbonImmutable::parse('2026-07-25 09:00:00', 'UTC'));

        $user = User::factory()->tracking()->inTimezone('Pacific/Auckland')->create();

        $meal = app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Dinner,
            entries: [new MealEntryDraft(text: 'soup', foodItemId: null)],
        );

        $this->assertSame(
            '2026-07-20',
            $meal->eaten_at->setTimezone($user->timezone)->toDateString(),
        );
    }

    public function test_re_queueing_the_same_entry_does_not_duplicate_its_review_item(): void
    {
        $user = User::factory()->tracking()->create();

        app(LogMeal::class)(
            user: $user,
            date: CarbonImmutable::parse('2026-07-20'),
            mealType: MealType::Snack,
            entries: [new MealEntryDraft(text: 'mystery', foodItemId: null)],
        );

        $entry = FoodEntry::query()->sole();

        ReviewItem::query()->updateOrCreate(
            ['reviewable_type' => $entry->getMorphClass(), 'reviewable_id' => $entry->getKey()],
            ['text' => 'mystery', 'score' => null, 'status' => ReviewStatus::Pending],
        );

        $this->assertSame(1, ReviewItem::query()->count());
    }
}
