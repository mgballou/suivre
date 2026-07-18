<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Enums\FlareIntensity;
use App\Events\Conditions\FlareEventLogged;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use App\Services\Conditions\Actions\LogFlareEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

it('logs a flare event and dispatches the event after commit', function (): void {
    $condition = Condition::factory()->createQuietly();
    $user = $condition->user;

    $flareEvent = app(LogFlareEvent::class)(
        $user,
        $condition,
        CarbonImmutable::parse('2026-07-06 09:00:00'),
        FlareIntensity::Moderate,
        90,
        'Flared after lunch.',
    );

    expect($flareEvent)->toBeInstanceOf(FlareEvent::class);
    expect($flareEvent->intensity)->toBe(FlareIntensity::Moderate);
    expect($flareEvent->duration_minutes)->toBe(90);
    $this->assertDatabaseHas('flare_events', [
        'id' => $flareEvent->id,
        'user_id' => $user->id,
        'condition_id' => $condition->id,
        'intensity' => 2,
    ]);
    Event::assertDispatched(FlareEventLogged::class, function (FlareEventLogged $event) use ($flareEvent): bool {
        expect($event->flareEvent->id)->toBe($flareEvent->id);

        return true;
    });
});

it('logs a flare event without an optional duration or note', function (): void {
    $condition = Condition::factory()->createQuietly();

    $flareEvent = app(LogFlareEvent::class)(
        $condition->user,
        $condition,
        CarbonImmutable::parse('2026-07-06 09:00:00'),
        FlareIntensity::Mild,
        null,
        null,
    );

    expect($flareEvent->duration_minutes)->toBeNull();
    expect($flareEvent->note)->toBeNull();
});

it('rejects logging a flare for a condition owned by another user', function (): void {
    $condition = Condition::factory()->createQuietly();
    $intruder = User::factory()->createQuietly();

    expect(fn () => app(LogFlareEvent::class)(
        $intruder,
        $condition,
        CarbonImmutable::parse('2026-07-06 09:00:00'),
        FlareIntensity::Severe,
        null,
        null,
    ))->toThrow(ConditionNotOwnedException::class);
});
