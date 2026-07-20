<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\MealType;
use App\Models\FoodEntry;
use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;

it('persists a meal for a user', function (): void {
    $user = User::factory()->createQuietly();

    $meal = Meal::factory()->for($user)->createQuietly();

    expect($meal->exists)->toBeTrue();
    $this->assertDatabaseHas('meals', [
        'id' => $meal->id,
        'user_id' => $user->id,
    ]);
});

it('round-trips the meal type enum and immutable eaten_at', function (): void {
    $meal = Meal::factory()->createQuietly([
        'meal_type' => MealType::Dinner,
        'eaten_at' => CarbonImmutable::parse('2026-07-06 19:30:00'),
    ]);

    $fresh = $meal->fresh();

    expect($fresh->meal_type)->toBe(MealType::Dinner);
    expect($fresh->eaten_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('stores the meal type enum as its string backing value', function (): void {
    $meal = Meal::factory()->ofType(MealType::Snack)->createQuietly();

    $this->assertDatabaseHas('meals', [
        'id' => $meal->id,
        'meal_type' => 'snack',
    ]);
});

it('has many food entries', function (): void {
    $meal = Meal::factory()->createQuietly();
    FoodEntry::factory()->count(3)->forMeal($meal)->createQuietly();

    expect($meal->entries()->count())->toBe(3);
    expect($meal->load('entries')->entries->first())->toBeInstanceOf(FoodEntry::class);
});

it('cascades deletion to its food entries', function (): void {
    $meal = Meal::factory()->createQuietly();
    $entry = FoodEntry::factory()->forMeal($meal)->createQuietly();

    $meal->delete();

    $this->assertDatabaseMissing('food_entries', ['id' => $entry->id]);
});

it('resolves the day it attaches to in the owning user timezone', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();
    $meal = Meal::factory()
        ->for($user)
        ->eatenAt(CarbonImmutable::parse('2026-07-07T02:00:00Z'))
        ->createQuietly();

    expect($meal->load('user')->date?->toDateString())->toBe('2026-07-06');
});

it('yields a null day when the owning user is not loaded', function (): void {
    $meal = Meal::factory()->createQuietly();

    expect($meal->fresh()->date)->toBeNull();
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new Meal())->getMorphClass())->toBe('meals');
});
