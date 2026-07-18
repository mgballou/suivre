<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\FlareIntensity;
use App\Models\Condition;
use App\Models\FlareEvent;
use Carbon\CarbonImmutable;

it('persists a flare event for a condition', function (): void {
    $condition = Condition::factory()->createQuietly();

    $flareEvent = FlareEvent::factory()->forCondition($condition)->createQuietly();

    expect($flareEvent->exists)->toBeTrue();
    $this->assertDatabaseHas('flare_events', [
        'id' => $flareEvent->id,
        'condition_id' => $condition->id,
        'user_id' => $condition->user_id,
    ]);
});

it('round-trips the intensity enum and immutable occurred_at', function (): void {
    $flareEvent = FlareEvent::factory()->createQuietly([
        'intensity' => FlareIntensity::Severe,
        'occurred_at' => CarbonImmutable::parse('2026-07-06 08:30:00'),
    ]);

    $fresh = $flareEvent->fresh();

    expect($fresh->intensity)->toBe(FlareIntensity::Severe);
    expect($fresh->occurred_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('stores the intensity enum as its integer backing value', function (): void {
    $flareEvent = FlareEvent::factory()->createQuietly([
        'intensity' => FlareIntensity::Severe,
    ]);

    $this->assertDatabaseHas('flare_events', [
        'id' => $flareEvent->id,
        'intensity' => 3,
    ]);
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new FlareEvent())->getMorphClass())->toBe('flare_events');
});
