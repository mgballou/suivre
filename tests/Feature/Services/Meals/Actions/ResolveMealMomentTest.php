<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Meals\Actions;

use App\Enums\MealType;
use App\Models\User;
use App\Services\Meals\Actions\ResolveMealMoment;
use Carbon\CarbonImmutable;

it('stamps a meal logged today with the moment it is logged', function (): void {
    $now = CarbonImmutable::parse('2026-07-15 14:32:00', 'UTC');

    $moment = app(ResolveMealMoment::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        MealType::Lunch,
        $now,
    );

    expect($moment->equalTo($now))->toBeTrue();
});

it('places a back-filled meal at its slots conventional hour', function (): void {
    $user = User::factory()->createQuietly();
    $now = CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC');

    $breakfast = app(ResolveMealMoment::class)($user, CarbonImmutable::parse('2026-07-15'), MealType::Breakfast, $now);
    $dinner = app(ResolveMealMoment::class)($user, CarbonImmutable::parse('2026-07-15'), MealType::Dinner, $now);

    expect($breakfast->setTimezone($user->timezone)->format('H:i'))->toBe('08:00');
    expect($dinner->setTimezone($user->timezone)->format('H:i'))->toBe('19:00');
});

it('orders a back-filled days meals the way they were eaten', function (): void {
    $user = User::factory()->createQuietly();
    $now = CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC');
    $date = CarbonImmutable::parse('2026-07-15');

    $moments = array_map(
        static fn (MealType $type): CarbonImmutable => app(ResolveMealMoment::class)($user, $date, $type, $now),
        [MealType::Breakfast, MealType::Lunch, MealType::Dinner],
    );

    expect($moments[0]->lessThan($moments[1]))->toBeTrue();
    expect($moments[1]->lessThan($moments[2]))->toBeTrue();
});

it('keeps a back-filled meal on its own day in a far-offset timezone', function (): void {
    // The reason the hours are not midnight: a boundary stamp read back in
    // another zone lands on the neighbouring day.
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    $moment = app(ResolveMealMoment::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        MealType::Breakfast,
        CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'),
    );

    expect($moment->setTimezone($user->timezone)->toDateString())->toBe('2026-07-15');
});

it('returns the instant in UTC so the datetime cast cannot shift it', function (): void {
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    $moment = app(ResolveMealMoment::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        MealType::Dinner,
        CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'),
    );

    expect($moment->timezone->getName())->toBe('UTC');
});
