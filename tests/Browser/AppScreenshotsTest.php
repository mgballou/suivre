<?php

declare(strict_types=1);

use App\Enums\ConditionHue;
use App\Enums\FlareIntensity;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FlareEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * Baseline app screenshots. Run on demand (capture-screenshots skill) or by the
 * screenshots.yml CI bot on UI-touching PRs, which posts the PNGs to the PR.
 * A stable seeded identity keeps avatar/initials identical across re-runs.
 */

beforeEach(function (): void {
    $this->user = User::factory()->tracking()->create([
        'name' => 'Sam Rivard',
        'email' => 'sam@suivre.test',
    ]);
    $this->actingAs($this->user);
});

it('captures the filament admin dashboard', function (): void {
    $this->actingAs(User::factory()->admin()->tracking()->create([
        'name' => 'Ada Fournier',
        'email' => 'ada@suivre.test',
    ]));

    visit('/admin')
        ->assertSee('Dashboard')
        ->screenshot(filename: 'app/01-admin-dashboard');
});

it('captures the calendar', function (): void {
    visit('/calendar')
        ->assertNoSmoke()
        ->screenshot(filename: 'app/02-calendar');
});

it('captures first-run onboarding', function (): void {
    $this->user->conditions()->delete();

    visit('/onboarding/conditions')
        ->assertNoSmoke()
        ->screenshot(filename: 'app/03-onboarding-conditions');
});

it('captures the day with its condition ratings and flare entry', function (): void {
    $this->user->conditions()->update(['color' => ConditionHue::Clay->value]);

    Condition::factory()->for($this->user)->hue(ConditionHue::Indigo)->createQuietly(['name' => 'Brain fog']);
    Condition::factory()->for($this->user)->hue(ConditionHue::Moss)->createQuietly(['name' => 'Bloating']);

    ConditionLog::factory()->for($this->user)->for($this->user->conditions()->first())
        ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 7]);

    FlareEvent::factory()->for($this->user)->for($this->user->conditions()->first())
        ->createQuietly([
            'occurred_at' => CarbonImmutable::parse('2026-07-15 14:32:00'),
            'intensity' => FlareIntensity::Moderate,
            'duration_minutes' => 75,
            'note' => null,
        ]);

    visit('/day/2026-07-15')
        ->assertNoSmoke()
        ->screenshot(filename: 'app/04-day');
});

it('captures the conditions screen', function (): void {
    Condition::factory()->for($this->user)->hue(ConditionHue::Indigo)->createQuietly(['name' => 'Brain fog']);
    Condition::factory()->for($this->user)->hue(ConditionHue::Plum)->inactive()->createQuietly(['name' => 'Nausea']);

    visit('/settings/conditions')
        ->assertNoSmoke()
        ->screenshot(filename: 'app/05-conditions');
});
