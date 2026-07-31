<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Enums\RampStep;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use App\Services\Insights\Actions\BuildExposureTimeline;
use App\Services\Insights\Data\ExposureTimeline;
use Carbon\CarbonImmutable;

const TIMELINE_TODAY = '2026-07-30';

function timelineFor(User $user, int $range = 30): ExposureTimeline
{
    return app(BuildExposureTimeline::class)($user, CarbonImmutable::parse(TIMELINE_TODAY), $range);
}

/**
 * A catalog food carrying one category. Idempotent on the slug, so a test can
 * ask for the same tag on several days without minting a duplicate category.
 */
function tagged(string $slug): FoodItem
{
    $category = Category::query()->firstOrCreate(
        ['slug' => $slug],
        Category::factory()->raw(['name' => ucfirst($slug), 'slug' => $slug]),
    );

    return FoodItem::factory()
        ->named('food ' . $slug . ' ' . fake()->unique()->word())
        ->withCategories([$category])
        ->createQuietly();
}

function eaten(User $user, FoodItem $foodItem, int $daysAgo): void
{
    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse(TIMELINE_TODAY, 'UTC')->subDays($daysAgo)->addHours(12))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($foodItem)->createQuietly();
}

it('draws one column per day of the range, ending today', function (): void {
    $timeline = timelineFor(User::factory()->createQuietly());

    expect($timeline->days)->toHaveCount(30);
    expect($timeline->days[29]->date)->toBe(TIMELINE_TODAY);
    expect($timeline->days[0]->date)->toBe('2026-07-01');
});

it('offers a ninety day range as well', function (): void {
    expect(timelineFor(User::factory()->createQuietly(), 90)->days)->toHaveCount(90);
    expect(timelineFor(User::factory()->createQuietly(), 90)->rangeDays)->toBe(90);
});

it('falls back to the shortest range rather than honouring an invented one', function (): void {
    $timeline = timelineFor(User::factory()->createQuietly(), 4000);

    expect($timeline->rangeDays)->toBe(30);
    expect($timeline->days)->toHaveCount(30);
});

it('puts each day on the shared ramp rather than its raw rating', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()
        ->forCondition($condition)
        ->on(CarbonImmutable::parse(TIMELINE_TODAY))
        ->createQuietly(['intensity' => 8]);

    $timeline = timelineFor($user);

    expect($timeline->days[29]->level)->toBe(RampStep::fromRating(8));
    expect($timeline->days[28]->level)->toBe(RampStep::None);
});

it('aligns a tag row index for index with the days', function (): void {
    $user = User::factory()->createQuietly();

    eaten($user, tagged('dairy'), daysAgo: 0);
    eaten($user, tagged('dairy'), daysAgo: 2);

    $timeline = timelineFor($user);
    $tag = $timeline->tags[0];

    expect($tag->present)->toHaveCount(30);
    expect($tag->present[29])->toBeTrue();
    expect($tag->present[28])->toBeFalse();
    expect($tag->present[27])->toBeTrue();
});

it('counts the days a tag turned up on', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = tagged('dairy');

    eaten($user, $dairy, daysAgo: 0);
    eaten($user, $dairy, daysAgo: 0);
    eaten($user, $dairy, daysAgo: 5);

    expect(timelineFor($user)->tags[0]->days())->toBe(2);
});

it('puts the most frequent tags first, so co-travellers land next to each other', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = tagged('dairy');
    $gluten = tagged('gluten');

    eaten($user, $dairy, daysAgo: 0);
    eaten($user, $gluten, daysAgo: 0);
    eaten($user, $gluten, daysAgo: 1);
    eaten($user, $gluten, daysAgo: 2);

    $timeline = timelineFor($user);

    expect($timeline->tags[0]->slug)->toBe('gluten');
    expect($timeline->tags[1]->slug)->toBe('dairy');
});

it('shows two co-occurring tags marking the same columns', function (): void {
    // The whole reason the surface exists: a ranking cannot separate these, and
    // the timeline is where that becomes visible rather than being asserted.
    $user = User::factory()->createQuietly();
    $dairy = tagged('dairy');
    $sugar = tagged('added-sugar');

    foreach ([0, 3, 6] as $daysAgo) {
        eaten($user, $dairy, $daysAgo);
        eaten($user, $sugar, $daysAgo);
    }

    $timeline = timelineFor($user);

    expect($timeline->tags[0]->present)->toBe($timeline->tags[1]->present);
});

it('draws at most eight tag rows', function (): void {
    $user = User::factory()->createQuietly();

    foreach (range(1, 11) as $index) {
        eaten($user, tagged("tag-{$index}"), daysAgo: 0);
    }

    expect(timelineFor($user)->tags)->toHaveCount(8);
});

it('ignores food eaten before the range', function (): void {
    $user = User::factory()->createQuietly();

    eaten($user, tagged('dairy'), daysAgo: 40);

    expect(timelineFor($user)->tags)->toBe([]);
    expect(timelineFor($user, 90)->tags)->toHaveCount(1);
});

it('draws an empty ramp and no rows for a user with nothing logged', function (): void {
    $timeline = timelineFor(User::factory()->createQuietly());

    expect($timeline->tags)->toBe([]);
    expect(collect($timeline->days)->every(fn ($day): bool => $day->level === RampStep::None))->toBeTrue();
});
