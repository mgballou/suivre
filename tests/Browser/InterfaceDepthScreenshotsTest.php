<?php

declare(strict_types=1);

use App\Enums\ConditionHue;
use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\DailyCheckin;
use App\Models\FoodEntry;
use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * Review-boundary screenshots for the interface-depth stack (SUI-58/59/60).
 *
 * Not registered in phpunit.xml, so the quality gate never runs it. Its job is
 * to put every surface the stack touches in front of a reviewer at three widths
 * and in both schemes, from identical seeded data every run — a shot that
 * invents fresh fake data cannot be compared against the last one.
 *
 * SUI-58 changes nothing that renders, so the shots it produces are the
 * baseline the two tickets above it are read against.
 *
 * Names are prefixed `depth`/`DEPTH` because Pest files share one global
 * namespace: ReadmeScreenshotsTest already owns a SHOWN_MONTH.
 *
 * One artifact to expect rather than chase: a page taller than the viewport is
 * captured full-page, and the tab bar is `fixed`, so it appears part-way down
 * the image instead of at the bottom. That is the capture, not the layout.
 */

/** Phone, tablet, desktop. */
const DEPTH_WIDTHS = [
    'phone' => [390, 844],
    'tablet' => [768, 1024],
    'desktop' => [1280, 900],
];

const DEPTH_MONTH = '2026-08';

/** Three day states worth looking at: untouched, part-filled, reviewed. */
const DEPTH_FRESH_DAY = '2026-08-12';

const DEPTH_PARTIAL_DAY = '2026-08-13';

const DEPTH_REVIEWED_DAY = '2026-08-14';

/**
 * The colour scheme is a browser-context option, so it has to be chosen on the
 * pending page — before `on()` resolves it into a live one. Chaining
 * `inDarkMode()` after `on()` fails with "undefined method Webpage::inDarkMode()".
 */
function depthShot(string $url, string $scheme, string $size): object
{
    [$width, $height] = DEPTH_WIDTHS[$size];

    $pending = visit($url);

    return ($scheme === 'dark' ? $pending->inDarkMode() : $pending->inLightMode())
        ->on()->desktop()->resize($width, $height);
}

dataset('viewports', function (): iterable {
    foreach (array_keys(DEPTH_WIDTHS) as $size) {
        foreach (['light', 'dark'] as $scheme) {
            yield "{$size} {$scheme}" => [$size, $scheme];
        }
    }
});

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-08-14 09:00:00');

    $this->user = User::factory()->create([
        'name' => 'Sam Rivard',
        'email' => 'sam@suivre.test',
        'timezone' => 'America/New_York',
    ]);

    $joints = Condition::factory()->for($this->user)->hue(ConditionHue::Clay)
        ->createQuietly(['name' => 'Joint pain']);
    $fog = Condition::factory()->for($this->user)->hue(ConditionHue::Indigo)
        ->createQuietly(['name' => 'Brain fog']);

    // Part-filled: a check-in on file and nothing else.
    DailyCheckin::factory()->for($this->user)->createQuietly([
        'date' => DEPTH_PARTIAL_DAY,
        'mood' => MoodLevel::Good,
        'sleep' => SleepQuality::Good,
        'stress' => StressLevel::Low,
        'note' => null,
    ]);

    // Reviewed: everything on file.
    DailyCheckin::factory()->for($this->user)->createQuietly([
        'date' => DEPTH_REVIEWED_DAY,
        'mood' => MoodLevel::Good,
        'sleep' => SleepQuality::Poor,
        'stress' => StressLevel::High,
        'note' => 'Slept badly, knees stiff by mid-morning.',
    ]);

    foreach ([[$joints, 7], [$fog, 3]] as [$condition, $intensity]) {
        ConditionLog::factory()->forCondition($condition)->createQuietly([
            'date' => DEPTH_REVIEWED_DAY,
            'intensity' => $intensity,
        ]);
    }

    /*
     * Seeded in UTC, and that is load-bearing — the same trap ResolveMealMoment
     * documents. Eloquent's datetime cast writes a Carbon's wall-clock reading,
     * so handing it 12:30-04:00 stores 12:30 UTC and the meal reads back as
     * 08:30 in the user's own timezone.
     */
    $meal = Meal::factory()->for($this->user)->createQuietly([
        'eaten_at' => CarbonImmutable::parse(DEPTH_REVIEWED_DAY . ' 12:30', 'America/New_York')->utc(),
    ]);

    foreach (['Greek yogurt', 'Rye toast', 'Black coffee'] as $line) {
        FoodEntry::factory()->forMeal($meal)->createQuietly(['text' => $line]);
    }

    $this->actingAs($this->user);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('captures the day in each of its three states', function (string $size, string $scheme): void {
    $days = [
        'fresh' => DEPTH_FRESH_DAY,
        'partial' => DEPTH_PARTIAL_DAY,
        'reviewed' => DEPTH_REVIEWED_DAY,
    ];

    foreach ($days as $state => $date) {
        depthShot("/day/{$date}", $scheme, $size)
            ->assertNoSmoke()
            ->screenshot(filename: "depth/day-{$state}-{$size}-{$scheme}");
    }
})->with('viewports');

it('captures the calendar, whose day cells must not change', function (string $size, string $scheme): void {
    depthShot('/calendar/' . DEPTH_MONTH, $scheme, $size)
        ->assertNoSmoke()
        ->screenshot(filename: "depth/calendar-{$size}-{$scheme}");
})->with('viewports');

it('captures the insights surface', function (string $size, string $scheme): void {
    depthShot('/insights', $scheme, $size)
        ->assertNoSmoke()
        ->screenshot(filename: "depth/insights-{$size}-{$scheme}");
})->with('viewports');

it('captures the settings screens', function (string $size, string $scheme): void {
    $screens = [
        'profile' => '/settings/profile',
        'appearance' => '/settings/appearance',
        'conditions' => '/settings/conditions',
    ];

    foreach ($screens as $name => $url) {
        depthShot($url, $scheme, $size)
            ->assertNoSmoke()
            ->screenshot(filename: "depth/settings-{$name}-{$size}-{$scheme}");
    }
})->with('viewports');
