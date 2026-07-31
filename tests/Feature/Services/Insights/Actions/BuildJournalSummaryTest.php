<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Enums\ConditionHue;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use App\Services\Insights\Actions\BuildJournalSummary;
use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\JournalSummary;
use Carbon\CarbonImmutable;

const TODAY = '2026-07-30';

function summaryFor(User $user): JournalSummary
{
    return app(BuildJournalSummary::class)($user, CarbonImmutable::parse(TODAY));
}

function rate(Condition $condition, int $days): void
{
    for ($offset = 0; $offset < $days; $offset++) {
        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse(TODAY)->subDays($offset))
            ->createQuietly(['intensity' => 4]);
    }
}

function ate(User $user, FoodItem $foodItem, int $daysAgo): void
{
    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse(TODAY, $user->timezone)->subDays($daysAgo)->addHours(12)->utc())
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->resolvedToFoodItem($foodItem)->createQuietly();
}

it('reports readiness against the same threshold the engine gates on', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    rate($condition, days: 12);

    $summary = summaryFor($user);

    expect($summary->conditions)->toHaveCount(1);
    expect($summary->conditions[0]->loggedDays)->toBe(12);
    expect($summary->conditions[0]->requiredDays)->toBe(CorrelationThresholds::MINIMUM_LOGGED_DAYS);
    expect($summary->conditions[0]->remainingDays())->toBe(CorrelationThresholds::MINIMUM_LOGGED_DAYS - 12);
    expect($summary->conditions[0]->isReady())->toBeFalse();
});

it('counts readiness per condition, because the volume gate is per condition', function (): void {
    $user = User::factory()->createQuietly();
    $old = Condition::factory()->for($user)->createQuietly(['name' => 'Eczema']);
    $recent = Condition::factory()->for($user)->createQuietly(['name' => 'Migraine']);

    rate($old, days: 20);
    rate($recent, days: 3);

    $summary = summaryFor($user);

    expect($summary->conditions)->toHaveCount(2);
    expect($summary->conditions[0]->name)->toBe('Eczema');
    expect($summary->conditions[0]->loggedDays)->toBe(20);
    expect($summary->conditions[1]->name)->toBe('Migraine');
    expect($summary->conditions[1]->loggedDays)->toBe(3);
});

it('marks a condition ready once it clears the threshold', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    rate($condition, days: CorrelationThresholds::MINIMUM_LOGGED_DAYS);

    expect(summaryFor($user)->conditions[0]->isReady())->toBeTrue();
    expect(summaryFor($user)->conditions[0]->remainingDays())->toBe(0);
});

it('leaves stopped conditions out, since they are not accumulating', function (): void {
    $user = User::factory()->createQuietly();
    $stopped = Condition::factory()->for($user)->createQuietly(['is_active' => false]);

    rate($stopped, days: 5);

    expect(summaryFor($user)->conditions)->toBe([]);
});

it('carries the condition hue so the surface can colour itself', function (): void {
    $user = User::factory()->createQuietly();
    Condition::factory()->for($user)->createQuietly(['color' => ConditionHue::Marine]);

    expect(summaryFor($user)->conditions[0]->hue)->toBe(ConditionHue::Marine->value);
});

it('counts the days each trigger category turned up on', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy', 'slug' => 'dairy']);
    $milk = FoodItem::factory()->named('milk')->withCategories([$dairy])->createQuietly();

    ate($user, $milk, daysAgo: 0);
    ate($user, $milk, daysAgo: 1);
    ate($user, $milk, daysAgo: 2);

    $tags = summaryFor($user)->tags;

    expect($tags)->toHaveCount(1);
    expect($tags[0]->name)->toBe('Dairy');
    expect($tags[0]->days)->toBe(3);
});

it('counts a category once per day however many times it was eaten', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = Category::factory()->createQuietly(['slug' => 'dairy']);
    $milk = FoodItem::factory()->named('milk')->withCategories([$dairy])->createQuietly();

    ate($user, $milk, daysAgo: 0);
    ate($user, $milk, daysAgo: 0);

    expect(summaryFor($user)->tags[0]->days)->toBe(1);
});

it('orders tags by the days they appeared on', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = Category::factory()->createQuietly(['name' => 'Dairy', 'slug' => 'dairy']);
    $gluten = Category::factory()->createQuietly(['name' => 'Gluten', 'slug' => 'gluten']);
    $milk = FoodItem::factory()->named('milk')->withCategories([$dairy])->createQuietly();
    $bread = FoodItem::factory()->named('bread')->withCategories([$gluten])->createQuietly();

    ate($user, $milk, daysAgo: 0);
    ate($user, $bread, daysAgo: 0);
    ate($user, $bread, daysAgo: 1);
    ate($user, $bread, daysAgo: 2);

    $tags = summaryFor($user)->tags;

    expect($tags[0]->name)->toBe('Gluten');
    expect($tags[0]->days)->toBe(3);
    expect($tags[1]->name)->toBe('Dairy');
});

it('names at most six tags, so the order is never read as a ranking', function (): void {
    $user = User::factory()->createQuietly();

    foreach (range(1, 8) as $index) {
        $category = Category::factory()->createQuietly(['slug' => "tag-{$index}"]);
        $food = FoodItem::factory()->named("food {$index}")->withCategories([$category])->createQuietly();

        ate($user, $food, daysAgo: 0);
    }

    expect(summaryFor($user)->tags)->toHaveCount(6);
});

it('ignores food eaten before the window', function (): void {
    $user = User::factory()->createQuietly();
    $dairy = Category::factory()->createQuietly(['slug' => 'dairy']);
    $milk = FoodItem::factory()->named('milk')->withCategories([$dairy])->createQuietly();

    ate($user, $milk, daysAgo: 29);
    ate($user, $milk, daysAgo: 30);

    expect(summaryFor($user)->tags[0]->days)->toBe(1);
});

it('counts nothing for an entry the classifier never resolved', function (): void {
    $user = User::factory()->createQuietly();

    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse(TODAY)->addHours(12))
        ->createQuietly();

    FoodEntry::factory()->forMeal($meal)->pendingClassification()->createQuietly();

    expect(summaryFor($user)->tags)->toBe([]);
});

it('never counts another user\'s meals', function (): void {
    $user = User::factory()->createQuietly();
    $stranger = User::factory()->createQuietly();

    $dairy = Category::factory()->createQuietly(['slug' => 'dairy']);
    $milk = FoodItem::factory()->named('milk')->withCategories([$dairy])->createQuietly();

    ate($stranger, $milk, daysAgo: 0);

    expect(summaryFor($user)->tags)->toBe([]);
});

it('summarises an empty journal without failing', function (): void {
    $summary = summaryFor(User::factory()->createQuietly());

    expect($summary->conditions)->toBe([]);
    expect($summary->tags)->toBe([]);
});
