<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;
use App\Services\Journal\Actions\BuildDayView;
use Carbon\CarbonImmutable;

it('reads an unlogged day at ramp step 0 with no values', function (): void {
    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($view->level)->toBe(0);
    expect($view->mood)->toBeNull();
    expect($view->sleep)->toBeNull();
    expect($view->stress)->toBeNull();
    expect($view->note)->toBeNull();
    expect($view->isToday)->toBeFalse();
});

it('reads a logged day at ramp step 1 with its saved values', function (): void {
    $user = User::factory()->createQuietly();

    DailyCheckin::factory()
        ->for($user)
        ->on(CarbonImmutable::parse('2026-07-15'))
        ->createQuietly([
            'mood' => MoodLevel::Neutral,
            'sleep' => SleepQuality::Good,
            'stress' => StressLevel::Low,
            'note' => 'quiet day',
        ]);

    $view = app(BuildDayView::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->level)->toBe(1);
    expect($view->mood)->toBe(MoodLevel::Neutral->value);
    expect($view->sleep)->toBe(SleepQuality::Good->value);
    expect($view->stress)->toBe(StressLevel::Low->value);
    expect($view->note)->toBe('quiet day');
    expect($view->isToday)->toBeTrue();
});

it('carries the month the day belongs to, so the return link lands there', function (): void {
    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2025-12-31'),
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($view->month)->toBe('2025-12');
    expect($view->label)->toBe('Wednesday 31 December 2025');
});

it('projects each scale from its enum in declared order', function (): void {
    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect(array_keys($view->scales))->toBe(['mood', 'sleep', 'stress']);
    expect($view->scales['mood'])->toHaveCount(count(MoodLevel::cases()));
    expect($view->scales['sleep'])->toHaveCount(count(SleepQuality::cases()));
    expect($view->scales['stress'])->toHaveCount(count(StressLevel::cases()));
    expect($view->scales['stress'][0]->value)->toBe(StressLevel::Low->value);
    expect($view->scales['stress'][0]->label)->toBe(StressLevel::Low->getLabel());
});

it('ignores another users check-in for the same day', function (): void {
    DailyCheckin::factory()
        ->for(User::factory()->createQuietly())
        ->on(CarbonImmutable::parse('2026-07-15'))
        ->createQuietly(['mood' => MoodLevel::Good]);

    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->level)->toBe(0);
    expect($view->mood)->toBeNull();
});
