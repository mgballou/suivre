<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Models\User;
use App\Services\Journal\Actions\ResolveJournalBounds;
use Carbon\CarbonImmutable;

it('runs from the day the account began to the users own today', function (): void {
    $user = User::factory()->createQuietly(['created_at' => CarbonImmutable::parse('2026-04-02 08:00:00', 'UTC')]);

    $bounds = app(ResolveJournalBounds::class)($user, CarbonImmutable::parse('2026-07-06 08:00:00', 'UTC'));

    expect($bounds->earliest->toDateString())->toBe('2026-04-02');
    expect($bounds->latest->toDateString())->toBe('2026-07-06');
});

it('reads both edges in the users timezone rather than the servers', function (): void {
    $user = User::factory()
        ->inTimezone('Pacific/Auckland')
        ->createQuietly(['created_at' => CarbonImmutable::parse('2026-04-01 23:30:00', 'UTC')]);

    $bounds = app(ResolveJournalBounds::class)($user, CarbonImmutable::parse('2026-07-06 23:30:00', 'UTC'));

    expect($bounds->earliest->toDateString())->toBe('2026-04-02');
    expect($bounds->latest->toDateString())->toBe('2026-07-07');
});

it('covers every day from the first to today and nothing either side', function (): void {
    $user = User::factory()->createQuietly(['created_at' => CarbonImmutable::parse('2026-04-02 08:00:00', 'UTC')]);

    $bounds = app(ResolveJournalBounds::class)($user, CarbonImmutable::parse('2026-07-06 08:00:00', 'UTC'));

    expect($bounds->covers(CarbonImmutable::parse('2026-04-02')))->toBeTrue();
    expect($bounds->covers(CarbonImmutable::parse('2026-05-20')))->toBeTrue();
    expect($bounds->covers(CarbonImmutable::parse('2026-07-06')))->toBeTrue();
    expect($bounds->covers(CarbonImmutable::parse('2026-04-01')))->toBeFalse();
    expect($bounds->covers(CarbonImmutable::parse('2026-07-07')))->toBeFalse();
});

it('names the direction a day misses in', function (): void {
    $user = User::factory()->createQuietly(['created_at' => CarbonImmutable::parse('2026-04-02 08:00:00', 'UTC')]);

    $bounds = app(ResolveJournalBounds::class)($user, CarbonImmutable::parse('2026-07-06 08:00:00', 'UTC'));

    expect($bounds->precedes(CarbonImmutable::parse('2026-04-01')))->toBeTrue();
    expect($bounds->follows(CarbonImmutable::parse('2026-04-01')))->toBeFalse();
    expect($bounds->follows(CarbonImmutable::parse('9999-12-31')))->toBeTrue();
    expect($bounds->precedes(CarbonImmutable::parse('9999-12-31')))->toBeFalse();
});

it('leaves an account created later than now with today alone', function (): void {
    $user = User::factory()->createQuietly(['created_at' => CarbonImmutable::parse('2026-07-09 08:00:00', 'UTC')]);

    $bounds = app(ResolveJournalBounds::class)($user, CarbonImmutable::parse('2026-07-06 08:00:00', 'UTC'));

    expect($bounds->earliest->toDateString())->toBe('2026-07-06');
    expect($bounds->latest->toDateString())->toBe('2026-07-06');
});
