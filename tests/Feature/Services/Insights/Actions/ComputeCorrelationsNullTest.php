<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Models\Condition;
use App\Models\User;
use App\Services\Insights\Actions\ComputeCorrelations;
use App\Services\Insights\Data\CorrelationSuspect;
use Carbon\CarbonImmutable;
use Tests\Support\Insights\SyntheticJournal;

/**
 * What the engine says about a journal where nothing is a trigger.
 *
 * Every other engine test plants an effect and asks whether it is found. Until
 * D29 nothing planted nothing — which is the journal a new account is most
 * likely to be, and the case where the surface was wrong two times in five.
 *
 * The two tests pull in opposite directions on purpose. The first alone could
 * be passed by never flagging anything, so the second states the floor: a
 * trigger large enough to act on still has to clear. Together they are the
 * measurement any future change to the null has to beat.
 *
 * Measured on this scenario at the D29 criterion: 3.3% of null journals flag a
 * row, against 38.3% under the per-row criterion it replaced; a planted
 * 3.0-point trigger clears on 97.5% of draws, against 100%.
 *
 * The draws are deterministic — `SyntheticJournal` runs a fixed-seed linear
 * congruential generator — so these are measurements, not samples that drift
 * between runs. The bars carry headroom for a legitimate change to the null,
 * not for flakiness.
 *
 * Slow, so tagged out of the fast run. Run with
 * `herd php artisan test --group=monte-carlo`.
 */

/** Base rates spread across the range a real user's foods occupy. */
const MONTE_CARLO_RATES = [0.12, 0.15, 0.18, 0.20, 0.22, 0.25, 0.28, 0.32];

const MONTE_CARLO_DAYS = 120;

/**
 * The share of null journals allowed to flag a row. Nominal for a report-level
 * criterion at the 95th percentile is 5%.
 */
const PAGE_LEVEL_FALSE_ALARM_BAR = 0.15;

/**
 * The share of journals with a 3.0-point trigger in which it must still clear.
 * A trigger this size is the one the product exists to name; the bar is set
 * where a correction that silenced it would fail rather than pass quietly.
 */
const PLANTED_TRIGGER_DETECTION_BAR = 0.90;

/**
 * The rows that cleared, one entry per draw, on journals built from
 * `MONTE_CARLO_RATES` with `$effect` planted on the fourth tag.
 *
 * @return array<int, array<int, string>> the slugs clearing, per draw
 */
function monteCarloClearingSlugs(int $draws, int $seedBase, float $effect = 0.0): array
{
    $today = CarbonImmutable::parse('2026-07-21');
    $clearing = [];

    for ($draw = 0; $draw < $draws; $draw++) {
        // The draws outnumber faker's word list, and the category and food-item
        // factories each take a unique word the journal then overwrites.
        fake()->unique(reset: true);

        $user = User::factory()->createQuietly(['timezone' => 'Europe/London']);
        $condition = Condition::factory()->for($user)->createQuietly();

        $journal = new SyntheticJournal(days: MONTE_CARLO_DAYS, seed: $seedBase + $draw);

        foreach (MONTE_CARLO_RATES as $index => $rate) {
            $journal->tag(
                "tag-{$seedBase}-{$draw}-{$index}",
                rate: $rate,
                effect: $index === 3 ? $effect : 0.0,
            );
        }

        $journal->plant($user, $condition, $today);

        $suspects = array_filter(
            app(ComputeCorrelations::class)($user, $condition)->suspects(),
            static fn (CorrelationSuspect $suspect): bool => $suspect->clearsNoiseBand,
        );

        $slugs = [];

        foreach ($suspects as $suspect) {
            foreach ($suspect->tags as $tag) {
                $slugs[] = $tag->slug;
            }
        }

        $clearing[] = $slugs;
    }

    return $clearing;
}

it('flags a row on few enough journals where nothing is a trigger', function (): void {
    $clearing = monteCarloClearingSlugs(draws: 60, seedBase: 4100);

    $flagged = count(array_filter($clearing, static fn (array $slugs): bool => $slugs !== []));

    expect($flagged / count($clearing))->toBeLessThanOrEqual(PAGE_LEVEL_FALSE_ALARM_BAR);
})->group('monte-carlo');

it('still clears a planted trigger large enough to act on', function (): void {
    $seedBase = 9400;
    $clearing = monteCarloClearingSlugs(draws: 40, seedBase: $seedBase, effect: 3.0);

    $found = 0;

    foreach ($clearing as $draw => $slugs) {
        if (in_array("tag-{$seedBase}-{$draw}-3", $slugs, strict: true)) {
            $found++;
        }
    }

    expect($found / count($clearing))->toBeGreaterThanOrEqual(PLANTED_TRIGGER_DETECTION_BAR);
})->group('monte-carlo');
