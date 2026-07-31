<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\Actions\ResolveDayBounds;
use Carbon\CarbonImmutable;

it('bounds a day with the utc instants of local midnight and the next local midnight', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-07-06'));

    expect($bounds->startsAt->utc()->toIso8601ZuluString())->toBe('2026-07-06T04:00:00Z');
    expect($bounds->endsAt->utc()->toIso8601ZuluString())->toBe('2026-07-07T04:00:00Z');
});

it('bounds a day against utc when the user has no timezone of their own', function (): void {
    $user = User::factory()->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-07-06'));

    expect($bounds->startsAt->utc()->toIso8601ZuluString())->toBe('2026-07-06T00:00:00Z');
    expect($bounds->endsAt->utc()->toIso8601ZuluString())->toBe('2026-07-07T00:00:00Z');
});

it('spans twenty five hours on the day the clocks go back', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-11-01'));

    expect($bounds->startsAt->diffInHours($bounds->endsAt))->toBe(25.0);
});

it('spans twenty three hours on the day the clocks go forward', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-03-08'));

    expect($bounds->startsAt->diffInHours($bounds->endsAt))->toBe(23.0);
});

it('bounds a day whose local midnight is skipped by a dst transition', function (): void {
    $user = User::factory()->inTimezone('America/Santiago')->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-09-06'));

    expect($bounds->startsAt->utc()->toIso8601ZuluString())->toBe('2026-09-06T04:00:00Z');
    expect($bounds->endsAt->utc()->toIso8601ZuluString())->toBe('2026-09-07T03:00:00Z');
});

it('hands the bounds back in utc, because they are used as query bindings', function (): void {
    // Laravel formats a Carbon binding in the Carbon's own timezone without
    // converting it, so a local-zone bound compares a wall-clock string against
    // UTC timestamp columns and selects the wrong window by the user's offset.
    // Every other assertion in this file calls ->utc() first, which is why this
    // one has to exist.
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    $bounds = app(ResolveDayBounds::class)($user, CarbonImmutable::parse('2026-07-06'));

    expect($bounds->startsAt->timezone->getName())->toBe('UTC');
    expect($bounds->endsAt->timezone->getName())->toBe('UTC');
});

it('leaves no gap between one day and the next', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();
    $resolve = app(ResolveDayBounds::class);

    $day = $resolve($user, CarbonImmutable::parse('2026-11-01'));
    $nextDay = $resolve($user, CarbonImmutable::parse('2026-11-02'));

    expect($day->endsAt->equalTo($nextDay->startsAt))->toBeTrue();
});
