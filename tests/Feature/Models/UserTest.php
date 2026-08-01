<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Condition;
use App\Models\DailyCheckin;
use App\Models\Meal;
use App\Models\User;

it('reads its one role as a scalar', function (): void {
    $user = User::factory()->admin()->createQuietly();

    expect($user->load('roles')->role)->toBe(UserRole::Admin);
});

it('reports no role rather than lazy-loading one', function (): void {
    // Strict mode would throw on the lazy load, at whichever render site forgot
    // to eager-load — so the accessor answers null and the caller loads.
    $user = User::query()->findOrFail(User::factory()->createQuietly()->id);

    expect($user->relationLoaded('roles'))->toBeFalse();
    expect($user->role)->toBeNull();
});

it('has no journal when it has logged nothing', function (): void {
    expect(User::factory()->createQuietly()->hasJournal())->toBeFalse();
});

it('has a journal once anything is logged', function (string $model): void {
    $user = User::factory()->createQuietly();

    $model::factory()->for($user)->createQuietly();

    expect($user->hasJournal())->toBeTrue();
})->with([
    'a meal' => [Meal::class],
    'a check-in' => [DailyCheckin::class],
    'a condition' => [Condition::class],
]);

it('answers the same from loaded counts as from queries', function (): void {
    // The counted path exists so a Filament table does not issue three EXISTS
    // statements per row; it must not disagree with the path it replaces.
    $empty = User::factory()->createQuietly();
    $logged = User::factory()->createQuietly();
    Meal::factory()->for($logged)->createQuietly();

    $counted = User::query()
        ->withCount(['meals', 'dailyCheckins', 'conditions'])
        ->findMany([$empty->id, $logged->id])
        ->keyBy('id');

    expect($counted[$empty->id]->hasJournal())->toBe($empty->hasJournal());
    expect($counted[$logged->id]->hasJournal())->toBe($logged->hasJournal());
    expect($counted[$logged->id]->hasJournal())->toBeTrue();
});
