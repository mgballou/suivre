<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;

it('resolves an instant to the calendar date it falls on in the user timezone', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();

    $date = app(ResolveUserDay::class)($user, CarbonImmutable::parse('2026-07-07T02:00:00Z'));

    expect($date->toDateString())->toBe('2026-07-06');
});

it('resolves against utc when the user has no timezone of their own', function (): void {
    $user = User::factory()->createQuietly();

    $date = app(ResolveUserDay::class)($user, CarbonImmutable::parse('2026-07-07T02:00:00Z'));

    expect($user->timezone)->toBe('UTC');
    expect($date->toDateString())->toBe('2026-07-07');
});

it('resolves the same wall-clock utc instant to different dates either side of a dst transition', function (): void {
    $user = User::factory()->inTimezone('America/New_York')->createQuietly();
    $resolve = app(ResolveUserDay::class);

    $beforeTransition = $resolve($user, CarbonImmutable::parse('2026-03-08T04:30:00Z'));
    $afterTransition = $resolve($user, CarbonImmutable::parse('2026-03-09T04:30:00Z'));

    expect($beforeTransition->toDateString())->toBe('2026-03-07');
    expect($afterTransition->toDateString())->toBe('2026-03-09');
});

it('resolves a day whose local midnight is skipped by a dst transition', function (): void {
    $user = User::factory()->inTimezone('America/Santiago')->createQuietly();

    $date = app(ResolveUserDay::class)($user, CarbonImmutable::parse('2026-09-06T04:30:00Z'));

    expect($date->toDateString())->toBe('2026-09-06');
    expect($date->format('H:i'))->toBe('01:00');
});
