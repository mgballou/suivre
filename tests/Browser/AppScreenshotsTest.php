<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Baseline app screenshots. Run on demand (capture-screenshots skill) or by the
 * screenshots.yml CI bot on UI-touching PRs, which posts the PNGs to the PR.
 * A stable seeded identity keeps avatar/initials identical across re-runs.
 */

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'name' => 'Sam Rivard',
        'email' => 'sam@suivre.test',
    ]);
    $this->actingAs($this->user);
});

it('captures the filament admin dashboard', function (): void {
    visit('/admin')
        ->assertSee('Dashboard')
        ->screenshot(filename: 'app/01-admin-dashboard');
});

it('captures the calendar', function (): void {
    visit('/calendar')
        ->assertNoSmoke()
        ->screenshot(filename: 'app/02-calendar');
});
