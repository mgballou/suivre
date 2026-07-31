<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Models\User;
use App\Services\Conditions\Actions\ResolveFlareMoment;
use Carbon\CarbonImmutable;

it('stamps a flare logged today with the moment it is logged', function (): void {
    $now = CarbonImmutable::parse('2026-07-15 14:32:00', 'UTC');

    $moment = app(ResolveFlareMoment::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        $now,
    );

    expect($moment->format('Y-m-d H:i'))->toBe('2026-07-15 14:32');
});

it('places a back-filled flare at midday, where no timezone can move its day', function (): void {
    $moment = app(ResolveFlareMoment::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'),
    );

    expect($moment->format('Y-m-d H:i'))->toBe('2026-07-15 12:00');
});

it('reads today in the users timezone, not the servers', function (): void {
    // 23:30 UTC on the 15th is already the 16th in Auckland (UTC+12 in July).
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();
    $now = CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC');

    $today = app(ResolveFlareMoment::class)($user, CarbonImmutable::parse('2026-07-16'), $now);
    $backFilled = app(ResolveFlareMoment::class)($user, CarbonImmutable::parse('2026-07-15'), $now);

    expect($today->format('Y-m-d H:i'))->toBe('2026-07-16 11:30');
    expect($backFilled->format('Y-m-d H:i'))->toBe('2026-07-15 12:00');
});
