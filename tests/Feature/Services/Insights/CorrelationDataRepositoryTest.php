<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights;

use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use App\Services\Insights\CorrelationDataRepository;
use Carbon\CarbonImmutable;

it('groups a meal by the day the user was living, not the stored instant', function (): void {
    $user = User::factory()->createQuietly(['timezone' => 'Pacific/Auckland']);
    $category = Category::factory()->createQuietly();
    $foodItem = FoodItem::factory()->withCategories([$category])->createQuietly();

    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 23:30', 'UTC'))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($foodItem)->createQuietly();

    $history = app(CorrelationDataRepository::class)->exposureHistory(
        $user,
        CarbonImmutable::parse('2026-07-19'),
        CarbonImmutable::parse('2026-07-22'),
    );

    expect($history->categoryIdsByDate)->toBe(['2026-07-21' => [$category->id]]);
});

it('carries the category name and slug through to the suspect tag', function (): void {
    $user = User::factory()->createQuietly();
    $category = Category::factory()->createQuietly(['name' => 'Dairy', 'slug' => 'dairy']);
    $foodItem = FoodItem::factory()->withCategories([$category])->createQuietly();

    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 12:00', 'UTC'))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($foodItem)->createQuietly();

    $history = app(CorrelationDataRepository::class)->exposureHistory(
        $user,
        CarbonImmutable::parse('2026-07-20'),
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($history->tags[$category->id]->name)->toBe('Dairy');
    expect($history->tags[$category->id]->slug)->toBe('dairy');
});

it('reads no tags off an entry still awaiting classification', function (): void {
    $user = User::factory()->createQuietly();

    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 12:00', 'UTC'))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->pendingClassification()->createQuietly();

    $history = app(CorrelationDataRepository::class)->exposureHistory(
        $user,
        CarbonImmutable::parse('2026-07-20'),
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($history->categoryIdsByDate)->toBe([]);
    expect($history->tags)->toBe([]);
});

it('lists a day’s tags once however many entries resolve to them', function (): void {
    $user = User::factory()->createQuietly();
    $category = Category::factory()->createQuietly();
    $first = FoodItem::factory()->withCategories([$category])->createQuietly();
    $second = FoodItem::factory()->withCategories([$category])->createQuietly();

    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 12:00', 'UTC'))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($first)->createQuietly();
    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($second)->createQuietly();

    $history = app(CorrelationDataRepository::class)->exposureHistory(
        $user,
        CarbonImmutable::parse('2026-07-20'),
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($history->categoryIdsByDate['2026-07-20'])->toBe([$category->id]);
});

it('reads one condition’s ratings and leaves another condition’s alone', function (): void {
    $user = User::factory()->createQuietly();
    $tracked = Condition::factory()->for($user)->createQuietly();
    $other = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()
        ->forCondition($tracked)
        ->on(CarbonImmutable::parse('2026-07-20'))
        ->createQuietly(['intensity' => 6]);

    ConditionLog::factory()
        ->forCondition($other)
        ->on(CarbonImmutable::parse('2026-07-20'))
        ->createQuietly(['intensity' => 1]);

    $intensity = app(CorrelationDataRepository::class)->dailyIntensity($user, $tracked);

    expect($intensity)->toBe(['2026-07-20' => 6]);
});

it('reads a meal day off the day the user was living, not the stored instant', function (): void {
    $user = User::factory()->createQuietly(['timezone' => 'Pacific/Auckland']);

    Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 23:30', 'UTC'))
        ->createQuietly();

    $days = app(CorrelationDataRepository::class)->mealDays(
        $user,
        CarbonImmutable::parse('2026-07-19'),
        CarbonImmutable::parse('2026-07-22'),
    );

    expect($days)->toBe(['2026-07-21' => true]);
});

it('counts a meal with nothing classified in it as a day the user logged', function (): void {
    $user = User::factory()->createQuietly();

    Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-20 12:00', 'UTC'))
        ->createQuietly();

    $days = app(CorrelationDataRepository::class)->mealDays($user);

    expect($days)->toBe(['2026-07-20' => true]);
});

it('counts only the rated days a meal was logged on', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();
    $other = Condition::factory()->for($user)->createQuietly();

    foreach (['2026-07-18', '2026-07-19', '2026-07-20'] as $date) {
        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse($date))
            ->createQuietly(['intensity' => 3]);
    }

    ConditionLog::factory()
        ->forCondition($other)
        ->on(CarbonImmutable::parse('2026-07-18'))
        ->createQuietly(['intensity' => 3]);

    foreach (['2026-07-18 12:00', '2026-07-19 12:00'] as $at) {
        Meal::factory()->for($user)->eatenAt(CarbonImmutable::parse($at, 'UTC'))->createQuietly();
    }

    $counts = app(CorrelationDataRepository::class)->comparableDayCounts($user);

    expect($counts[$condition->id])->toBe(2);
    expect($counts[$other->id])->toBe(1);
});

it('counts no comparable days for a user who has logged no meals', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()
        ->forCondition($condition)
        ->on(CarbonImmutable::parse('2026-07-18'))
        ->createQuietly(['intensity' => 3]);

    expect(app(CorrelationDataRepository::class)->comparableDayCounts($user))->toBe([]);
});
