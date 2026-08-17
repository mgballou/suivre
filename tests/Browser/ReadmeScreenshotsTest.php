<?php

declare(strict_types=1);

use App\Enums\ConditionHue;
use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\Condition;
use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\Insights\SyntheticJournal;

/*
 * The shots the README embeds. Kept rather than thrown away because the front
 * page has to be regenerated whenever the surfaces it shows change, and kept
 * out of AppScreenshotsTest because that one is the PR bot's and runs on every
 * UI-touching push.
 *
 * Not in phpunit.xml's suites, so the quality gate never runs it. Refresh with:
 *
 *   npm run build
 *   mkdir -p tests/Browser/Screenshots/readme
 *   herd php artisan test tests/Browser/ReadmeScreenshotsTest.php
 *   cp tests/Browser/Screenshots/readme/*.png docs/assets/
 */

/**
 * Enough history for the ranking to exist at all — the volume gate refuses to
 * rank under ninety logged days, so a shorter journal photographs the waiting
 * state instead of the product.
 */
const JOURNAL_DAYS = 150;

/** The month the calendar and day shots are taken from: complete, and past. */
const SHOWN_MONTH = '2026-07';

const SHOWN_DAY = '2026-07-15';

beforeEach(function (): void {
    $this->user = User::factory()->createQuietly([
        'name' => 'Sam Rivard',
        'email' => 'sam@suivre.test',
        'timezone' => 'America/New_York',
    ]);

    $this->condition = Condition::factory()
        ->for($this->user)
        ->hue(ConditionHue::Clay)
        ->createQuietly(['name' => 'Joint pain']);

    Condition::factory()->for($this->user)->hue(ConditionHue::Indigo)
        ->createQuietly(['name' => 'Brain fog']);

    // The planted trigger is dairy, lagging a day or two behind the meal; sugar
    // co-fires with it, which is the confound D24 exists to refuse to untangle.
    (new SyntheticJournal(days: JOURNAL_DAYS, seed: 61))
        ->tag('dairy', rate: 0.15, effect: 3.0)
        ->tag('added sugar', rate: 0.15)
        ->tag('gluten', rate: 0.20)
        ->tag('caffeine', rate: 0.25)
        ->tag('nightshade', rate: 0.12)
        ->coOccur('dairy', 'added sugar', strength: 0.35)
        ->plant($this->user, $this->condition, CarbonImmutable::now($this->user->timezone));

    foreach (['2026-07-13', '2026-07-14', SHOWN_DAY, '2026-07-16'] as $date) {
        DailyCheckin::factory()
            ->for($this->user)
            ->on(CarbonImmutable::parse($date))
            ->createQuietly([
                'sleep' => SleepQuality::Poor,
                'mood' => MoodLevel::Low,
                'stress' => StressLevel::High,
                'note' => null,
            ]);
    }

    $this->actingAs($this->user);
});

/**
 * Drop the cards below the ranking, so the insights shot is the section the
 * README is talking about rather than a metre of page. Everything left in the
 * frame is the page's own render — nothing is added or restyled.
 */
const CROP_TO_RANKING = <<<'JS'
(() => {
    const column = document.querySelector('main div.flex.flex-1.flex-col');
    [...column.children].slice(3).forEach((card) => card.remove());
    column.style.paddingBottom = '1.5rem';
})()
JS;

/**
 * The colour scheme is a browser-context option, so it has to be chosen on the
 * pending page — before `on()` resolves it into a live one.
 */
function readmePage(string $url, string $scheme, int $width, int $height): object
{
    $pending = visit($url);

    return ($scheme === 'dark' ? $pending->inDarkMode() : $pending->inLightMode())
        ->on()
        ->desktop()
        ->resize($width, $height);
}

it('captures the calendar in both schemes', function (): void {
    foreach (['light', 'dark'] as $scheme) {
        $page = readmePage('/calendar/' . SHOWN_MONTH, $scheme, 1400, 760);

        $page->assertNoSmoke()->assertSee('July 2026');
        $page->screenshot(filename: "readme/calendar-{$scheme}");
    }
});

it('captures the ranked suspects in both schemes', function (): void {
    foreach (['light', 'dark'] as $scheme) {
        $page = readmePage('/insights', $scheme, 1400, 900);

        // The ranking is a deferred prop: asserting a suspect's own copy waits
        // out the follow-up request rather than photographing the skeleton.
        $page->assertNoSmoke()->assertSee('Worth noticing')->assertSee('strongest');
        $page->script(CROP_TO_RANKING);
        $page->screenshot(filename: "readme/insights-{$scheme}");
    }
});

it('captures the operator backstage', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly([
        'name' => 'Ada Fournier',
        'email' => 'ada@suivre.test',
    ]));

    $page = readmePage('/admin/meals', 'light', 1400, 860);

    $page->assertSee('Meals')->wait(2);
    $page->screenshot(filename: 'readme/backstage-light');
});
