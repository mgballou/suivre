<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Enums\FlareIntensity;
use App\Models\Condition;
use App\Models\FlareEvent;
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

    expect($moment->equalTo($now))->toBeTrue();
});

it('places a back-filled flare at midday, where no timezone can move its day', function (): void {
    $user = User::factory()->createQuietly();

    $moment = app(ResolveFlareMoment::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'),
    );

    expect($moment->setTimezone($user->timezone)->format('Y-m-d H:i'))->toBe('2026-07-15 12:00');
});

it('reads today in the users timezone, not the servers', function (): void {
    // 23:30 UTC on the 15th is already the 16th in Auckland (UTC+12 in July).
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();
    $now = CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC');

    $today = app(ResolveFlareMoment::class)($user, CarbonImmutable::parse('2026-07-16'), $now);
    $backFilled = app(ResolveFlareMoment::class)($user, CarbonImmutable::parse('2026-07-15'), $now);

    expect($today->setTimezone($user->timezone)->format('Y-m-d H:i'))->toBe('2026-07-16 11:30');
    expect($backFilled->setTimezone($user->timezone)->format('Y-m-d H:i'))->toBe('2026-07-15 12:00');
});

it('returns the instant in UTC so the datetime cast cannot shift it', function (): void {
    // Eloquent's cast writes a Carbon's wall-clock reading and reads it back as
    // the app timezone. A moment carrying +12:00 would therefore be stored
    // twelve hours late. Asserting the zone here is what keeps that from
    // silently returning.
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    $moment = app(ResolveFlareMoment::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'),
    );

    expect($moment->timezone->getName())->toBe('UTC');
});

it('survives the round trip through the model at the users real local time', function (): void {
    // The regression this file exists for: a flare logged at 21:00 in Auckland
    // was stored as 21:00 UTC and read back to the user as 09:00 the next day.
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();
    $now = CarbonImmutable::parse('2026-07-25 09:00:00', 'UTC');

    $flare = FlareEvent::query()->create([
        'user_id' => $user->id,
        'condition_id' => $condition->id,
        'occurred_at' => app(ResolveFlareMoment::class)($user, CarbonImmutable::parse('2026-07-25'), $now),
        'intensity' => FlareIntensity::cases()[0],
        'duration_minutes' => null,
        'note' => null,
    ]);

    $stored = $flare->refresh()->occurred_at->setTimezone($user->timezone);

    expect($stored->format('Y-m-d H:i'))->toBe($now->setTimezone($user->timezone)->format('Y-m-d H:i'));
    expect($stored->format('Y-m-d H:i'))->toBe('2026-07-25 21:00');
});
