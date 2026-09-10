<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Enums\ConditionHue;
use App\Enums\FlareIntensity;
use App\Enums\MoodLevel;
use App\Enums\RampStep;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\DailyCheckin;
use App\Models\FlareEvent;
use App\Models\FoodEntry;
use App\Models\Meal;
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

it('lists the active conditions in name order with their rating for the day', function (): void {
    $user = User::factory()->createQuietly();

    $headache = Condition::factory()->for($user)->hue(ConditionHue::Moss)->createQuietly(['name' => 'Headache']);
    Condition::factory()->for($user)->createQuietly(['name' => 'Bloating']);

    ConditionLog::factory()
        ->for($user)
        ->for($headache)
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 7]);

    $view = app(BuildDayView::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->conditions)->toHaveCount(2);
    expect($view->conditions[0]->name)->toBe('Bloating');
    expect($view->conditions[0]->intensity)->toBeNull();
    expect($view->conditions[0]->level)->toBe(RampStep::None->value);
    expect($view->conditions[1]->name)->toBe('Headache');
    expect($view->conditions[1]->hue)->toBe(ConditionHue::Moss->value);
    expect($view->conditions[1]->intensity)->toBe(7);
    expect($view->conditions[1]->level)->toBe(RampStep::Strong->value);
});

it('leaves a stopped condition out while keeping its ratings on file', function (): void {
    $user = User::factory()->createQuietly();
    $stopped = Condition::factory()->for($user)->inactive()->createQuietly();

    ConditionLog::factory()
        ->for($user)
        ->for($stopped)
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 9]);

    $view = app(BuildDayView::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->conditions)->toBeEmpty();
    expect($view->level)->toBe(RampStep::None->value);
    expect(ConditionLog::query()->count())->toBe(1);
});

it('reads the day at its worst rating, so a calm condition cannot mask a severe one', function (): void {
    $user = User::factory()->createQuietly();
    $mild = Condition::factory()->for($user)->createQuietly(['name' => 'Bloating']);
    $severe = Condition::factory()->for($user)->createQuietly(['name' => 'Headache']);

    ConditionLog::factory()->for($user)->for($mild)
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 1]);
    ConditionLog::factory()->for($user)->for($severe)
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 10]);

    $view = app(BuildDayView::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->level)->toBe(RampStep::Severe->value);
});

it('ignores another users rating of their own condition', function (): void {
    $theirs = User::factory()->createQuietly();

    ConditionLog::factory()
        ->for($theirs)
        ->for(Condition::factory()->for($theirs))
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 9]);

    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->conditions)->toBeEmpty();
    expect($view->level)->toBe(RampStep::None->value);
});

it('offers the flare intensities from the domain enum', function (): void {
    $view = app(BuildDayView::class)(
        User::factory()->createQuietly(),
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->flareIntensities)->toHaveCount(count(FlareIntensity::cases()));
    expect($view->flareIntensities[0]->value)->toBe(FlareIntensity::Mild->value);
    expect($view->flareIntensities[0]->label)->toBe(FlareIntensity::Mild->getLabel());
});

it('lists the days flares in the order they happened', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly(['name' => 'Headache']);

    foreach (['2026-07-15 18:05:00', '2026-07-15 09:20:00'] as $at) {
        FlareEvent::factory()->for($user)->for($condition)->createQuietly([
            'occurred_at' => CarbonImmutable::parse($at),
            'intensity' => FlareIntensity::Moderate,
            'duration_minutes' => 45,
        ]);
    }

    $view = app(BuildDayView::class)(
        $user,
        CarbonImmutable::parse('2026-07-15'),
        CarbonImmutable::parse('2026-07-15'),
    );

    expect($view->flares)->toHaveCount(2);
    expect($view->flares[0]->time)->toBe('09:20');
    expect($view->flares[1]->time)->toBe('18:05');
    expect($view->flares[0]->duration)->toBe('45 min');
    expect($view->flares[0]->conditionName)->toBe('Headache');
});

it('describes an untouched day as what is not on file, never as what is left to do', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    $view = app(BuildDayView::class)($user, $date, $date)->toArray();

    expect(array_column($view['sections'], 'key'))
        ->toBe(['checkin', 'conditions', 'meals', 'flares']);
    expect(array_column($view['sections'], 'summary'))
        ->toBe(['Not recorded', 'Not rated', 'Nothing logged', 'None']);
    expect(array_column($view['sections'], 'recorded'))
        ->toBe([false, false, false, false]);
});

it('opens the first section with nothing on file', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    expect(app(BuildDayView::class)($user, $date, $date)->openSection)->toBe('checkin');
});

it('moves the open section past whatever the day already holds', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->on($date)->createQuietly([
        'sleep' => SleepQuality::Good,
    ]);

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->openSection)->toBe('conditions');
    expect($view->sections[0]->summary)->toBe('Slept well');
    expect($view->sections[0]->recorded)->toBeTrue();
});

it('leaves a reviewed day entirely collapsed', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $condition = $user->conditions()->sole();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->on($date)->createQuietly([
        'sleep' => SleepQuality::Good,
    ]);
    ConditionLog::factory()->forCondition($condition)->on($date)->createQuietly();
    Meal::factory()->for($user)->eatenAt($date->setTime(12, 0))->createQuietly();

    expect(app(BuildDayView::class)($user, $date, $date)->openSection)->toBeNull();
});

it('never opens the flare card, because an absent flare is a complete answer', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $condition = $user->conditions()->sole();
    $date = CarbonImmutable::parse('2026-08-12');

    DailyCheckin::factory()->for($user)->on($date)->createQuietly();
    ConditionLog::factory()->forCondition($condition)->on($date)->createQuietly();
    Meal::factory()->for($user)->eatenAt($date->setTime(12, 0))->createQuietly();

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->openSection)->not->toBe('flares');
    expect($view->sections[3]->summary)->toBe('None');
});

it('counts what a meal card holds in entries, because an entry is what the user typed', function (): void {
    $user = User::factory()->tracking()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    $meal = Meal::factory()->for($user)->eatenAt($date->setTime(12, 0))->createQuietly();
    FoodEntry::factory()->count(3)->forMeal($meal)->createQuietly();

    $view = app(BuildDayView::class)($user, $date, $date);

    expect($view->sections[2]->summary)->toBe('3 items');
});

it('says a condition-less account tracks none rather than that it has failed to rate any', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-08-12');

    expect(app(BuildDayView::class)($user, $date, $date)->sections[1]->summary)->toBe('None tracked');
});
