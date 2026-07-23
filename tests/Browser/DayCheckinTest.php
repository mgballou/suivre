<?php

declare(strict_types=1);

use App\Enums\MoodLevel;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;

/*
 * The daily ritual, driven end to end. This is the one flow where a browser
 * test earns its keep: the tap writes through client state, an Inertia POST and
 * a redirect, and none of that is observable from a server-side assertion.
 */

beforeEach(function (): void {
    $this->user = User::factory()->tracking()->create();
    $this->actingAs($this->user);
});

it('records a check-in in two taps, from the calendar', function (): void {
    visit('/calendar/2026-07')
        ->click('a[href$="/day/2026-07-15"]')
        ->assertPathIs('/day/2026-07-15')
        ->click('@mood-' . MoodLevel::Good->value)
        ->assertRadioSelected('mood', MoodLevel::Good->value)
        ->waitForEvent('networkidle')
        ->assertNoJavaScriptErrors();

    $checkin = DailyCheckin::query()->sole();

    expect($checkin->user_id)->toBe($this->user->id);
    expect($checkin->date->toDateString())->toBe('2026-07-15');
    expect($checkin->mood)->toBe(MoodLevel::Good);
});

it('shows the saved state when the day is revisited', function (): void {
    visit('/day/2026-07-15')
        ->click('@mood-' . MoodLevel::Low->value)
        ->click('@stress-' . StressLevel::High->value)
        ->assertRadioSelected('stress', StressLevel::High->value)
        ->waitForEvent('networkidle');

    visit('/day/2026-07-15')
        ->assertRadioSelected('mood', MoodLevel::Low->value)
        ->assertRadioSelected('stress', StressLevel::High->value)
        ->assertRadioNotSelected('mood', MoodLevel::Good->value);
});

it('edits the day in place rather than adding a second row', function (): void {
    visit('/day/2026-07-15')
        ->click('@mood-' . MoodLevel::Low->value)
        ->assertRadioSelected('mood', MoodLevel::Low->value)
        ->click('@mood-' . MoodLevel::Good->value)
        ->assertRadioSelected('mood', MoodLevel::Good->value)
        ->waitForEvent('networkidle');

    expect(DailyCheckin::query()->count())->toBe(1);
    expect(DailyCheckin::query()->sole()->mood)->toBe(MoodLevel::Good);
});

it('returns to the month the day belongs to', function (): void {
    visit('/day/2026-07-15')
        ->click('[aria-label="Back to calendar"]')
        ->assertPathIs('/calendar/2026-07');
});
