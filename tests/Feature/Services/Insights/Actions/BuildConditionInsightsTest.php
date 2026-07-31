<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use App\Services\Insights\Actions\BuildConditionInsights;
use App\Services\Insights\CorrelationThresholds;
use Carbon\CarbonImmutable;

const START = '2026-01-01';

/**
 * Rate the condition on every day of a run, worse across the exposure window
 * that follows each occurrence so there is a lift for the engine to find.
 *
 * Occurrences have to be sparse. The window is three days wide, so a tag eaten
 * every fourth day marks the whole calendar as exposed and leaves no baseline
 * to compare against — the engine then drops it as unmeasurable, which is
 * correct of it and useless as a fixture.
 *
 * @param  array<int, int>  $occurrences
 */
function journal(Condition $condition, int $days, array $occurrences = []): void
{
    $worse = [];

    foreach ($occurrences as $occurrence) {
        foreach (range(0, CorrelationThresholds::EXPOSURE_WINDOW_DAYS) as $lag) {
            $worse[$occurrence + $lag] = true;
        }
    }

    for ($offset = 0; $offset < $days; $offset++) {
        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse(START)->addDays($offset))
            ->createQuietly(['intensity' => isset($worse[$offset]) ? 8 : 2]);
    }
}

/**
 * @param  array<int, int>  $offsets
 */
function feed(User $user, FoodItem $foodItem, array $offsets): void
{
    foreach ($offsets as $offset) {
        $meal = Meal::factory()
            ->for($user)
            ->eatenAt(CarbonImmutable::parse(START, 'UTC')->addDays($offset)->addHours(12))
            ->createQuietly();

        FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($foodItem)->createQuietly();
    }
}

function taggedFood(string $name, string $slug): FoodItem
{
    $category = Category::factory()->createQuietly(['name' => ucfirst($slug), 'slug' => $slug]);

    return FoodItem::factory()->named($name)->withCategories([$category])->createQuietly();
}

it('returns nothing for a condition below the volume gate', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    journal($condition, days: CorrelationThresholds::MINIMUM_LOGGED_DAYS - 1);

    expect(app(BuildConditionInsights::class)($user))->toBeEmpty();
});

it('returns a ranking once the condition clears the gate', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly(['name' => 'Eczema']);

    $occurrences = range(0, 119, 10);
    journal($condition, days: 120, occurrences: $occurrences);
    feed($user, taggedFood('whole milk', 'dairy'), $occurrences);

    $insights = app(BuildConditionInsights::class)($user);

    expect($insights)->toHaveCount(1);
    expect($insights[0]->conditionName)->toBe('Eczema');
    expect($insights[0]->loggedDays)->toBe(120);
    expect($insights[0]->suspects)->not->toBeEmpty();
});

it('carries the sample sizes and timing each hint has to be read against', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    $occurrences = range(0, 119, 10);
    journal($condition, days: 120, occurrences: $occurrences);
    feed($user, taggedFood('whole milk', 'dairy'), $occurrences);

    $hint = app(BuildConditionInsights::class)($user)[0]->suspects[0];

    expect($hint->tags)->toContain('Dairy');
    expect($hint->exposedDays)->toBeGreaterThan(0);
    expect($hint->baselineDays)->toBeGreaterThan(0);
    expect($hint->lift)->toBeFloat();
});

it('reports a ready condition with an empty ranking rather than skipping it', function (): void {
    // Enough logged, but no meals at all — the engine has nothing to measure.
    // That is a different statement from "not enough logged yet", and the
    // surface has to be able to tell them apart.
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    journal($condition, days: CorrelationThresholds::MINIMUM_LOGGED_DAYS);

    $insights = app(BuildConditionInsights::class)($user);

    expect($insights)->toHaveCount(1);
    expect($insights[0]->suspects)->toBe([]);
});

it('names at most five suspects', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    journal($condition, days: 180, occurrences: range(0, 179, 10));

    // Phases five days apart on a forty-day period: every tag clears the
    // exposed and baseline minimums, and no two exposure windows overlap, so
    // the engine has eight separable tags to rank rather than one cluster.
    foreach (range(0, 7) as $index) {
        feed($user, taggedFood("food {$index}", "tag-{$index}"), range($index * 5, 179, 40));
    }

    expect(app(BuildConditionInsights::class)($user)[0]->suspects)
        ->toHaveCount(5);
});

it('leaves stopped conditions out', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly(['is_active' => false]);

    journal($condition, days: 120);

    expect(app(BuildConditionInsights::class)($user))->toBeEmpty();
});

it('never reaches another user\'s condition', function (): void {
    $user = User::factory()->createQuietly();
    $stranger = User::factory()->createQuietly();
    $condition = Condition::factory()->for($stranger)->createQuietly();

    journal($condition, days: 120);

    expect(app(BuildConditionInsights::class)($user))->toBeEmpty();
});
