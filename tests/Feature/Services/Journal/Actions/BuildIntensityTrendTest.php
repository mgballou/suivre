<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use App\Services\Journal\Actions\BuildIntensityTrend;
use Carbon\CarbonImmutable;

it('spans the window ending on the user’s today', function (): void {
    $user = User::factory()->createQuietly();

    $trend = app(BuildIntensityTrend::class)($user, CarbonImmutable::parse('2026-07-30'), 30);

    expect($trend->points)->toHaveCount(30);
    expect($trend->points[0]->date)->toBe('2026-07-01');
    expect($trend->points[29]->date)->toBe('2026-07-30');
});

it('carries a raw 0–10 rating, not a bucketed step', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()
        ->forCondition($condition)
        ->on(CarbonImmutable::parse('2026-07-30'))
        ->createQuietly(['intensity' => 7]);

    $trend = app(BuildIntensityTrend::class)($user, CarbonImmutable::parse('2026-07-30'), 30);

    expect($trend->points[29]->intensity)->toBe(7);
    expect($trend->loggedDays)->toBe(1);
});

it('leaves an unlogged day null so the line breaks rather than dropping to zero', function (): void {
    $user = User::factory()->createQuietly();

    $trend = app(BuildIntensityTrend::class)($user, CarbonImmutable::parse('2026-07-30'), 30);

    expect($trend->points[10]->intensity)->toBeNull();
    expect($trend->loggedDays)->toBe(0);
});

it('reports the worst rating per day as the day’s intensity', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-30');
    $calm = Condition::factory()->for($user)->createQuietly();
    $bad = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()->forCondition($calm)->on($date)->createQuietly(['intensity' => 3]);
    ConditionLog::factory()->forCondition($bad)->on($date)->createQuietly(['intensity' => 8]);

    $trend = app(BuildIntensityTrend::class)($user, $date, 30);

    expect($trend->points[29]->intensity)->toBe(8);
});

it('pre-formats the x-axis label so the client never derives a date', function (): void {
    $user = User::factory()->createQuietly();

    $trend = app(BuildIntensityTrend::class)($user, CarbonImmutable::parse('2026-07-30'), 30);

    expect($trend->points[29]->label)->toBe('30 Jul');
});
