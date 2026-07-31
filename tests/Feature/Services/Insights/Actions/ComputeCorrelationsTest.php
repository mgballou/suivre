<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Enums\CorrelationStatus;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Exceptions\Insights\InsufficientCorrelationDataException;
use App\Models\Condition;
use App\Models\User;
use App\Services\Insights\Actions\ComputeCorrelations;
use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\CorrelationSuspect;
use Carbon\CarbonImmutable;
use Tests\Support\Insights\SyntheticJournal;

/**
 * The scenarios below are PHP ports of the SUI-36 spike's synthetic logs. The
 * seeds are fixed so each run is reproducible, but numpy and the port's own
 * generator do not draw the same numbers, so the assertions are on properties
 * that hold across draws — ranking, sign, clearing the band, the separability
 * verdict — never on exact lifts.
 */
$today = CarbonImmutable::parse('2026-07-21');

/**
 * @return array<int, string>
 */
function suspectSlugs(CorrelationSuspect $suspect): array
{
    return array_map(static fn ($tag): string => $tag->slug, $suspect->tags);
}

it('refuses to rank below the minimum logged days', function () use ($today): void {
    $user = User::factory()->createQuietly(['timezone' => 'Europe/London']);
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 60, seed: 11))
        ->tag('trigger', rate: 0.25, effect: 3.0)
        ->tag('noise', rate: 0.2)
        ->plant($user, $condition, $today);

    $report = app(ComputeCorrelations::class)($user, $condition);

    expect($report->status)->toBe(CorrelationStatus::InsufficientData);
    expect($report->loggedDays)->toBe(60);
    expect($report->requiredDays)->toBe(CorrelationThresholds::MINIMUM_LOGGED_DAYS);
    expect($report->toArray()['suspects'])->toBe([]);
});

it('throws rather than hand back an empty ranking when there is not enough data', function () use ($today): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 30, seed: 12))
        ->tag('trigger', rate: 0.25, effect: 3.0)
        ->plant($user, $condition, $today);

    $report = app(ComputeCorrelations::class)($user, $condition);

    expect(fn () => $report->suspects())->toThrow(InsufficientCorrelationDataException::class);
});

it('surfaces a planted lagged trigger in the top three, clearing its noise band', function () use ($today): void {
    $user = User::factory()->createQuietly(['timezone' => 'Europe/London']);
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 120, seed: 2026))
        ->tag('trigger', rate: 0.22, effect: 3.0)
        ->tag('noise-a', rate: 0.30)
        ->tag('noise-b', rate: 0.25)
        ->tag('noise-c', rate: 0.20)
        ->tag('noise-d', rate: 0.15)
        ->tag('noise-e', rate: 0.28)
        ->plant($user, $condition, $today);

    $report = app(ComputeCorrelations::class)($user, $condition);
    $suspects = $report->suspects();

    expect($report->status)->toBe(CorrelationStatus::Ready);
    expect($report->loggedDays)->toBe(120);

    $topThree = array_merge(...array_map(suspectSlugs(...), array_slice($suspects, 0, 3)));
    expect($topThree)->toContain('trigger');

    $trigger = collect($suspects)->firstOrFail(
        static fn (CorrelationSuspect $suspect): bool => in_array('trigger', suspectSlugs($suspect), strict: true),
    );

    expect($trigger->granularity->isSingleTag())->toBeTrue();
    expect($trigger->clearsNoiseBand)->toBeTrue();
    expect($trigger->measurement->lift)->toBeGreaterThan(0.5);
    expect($trigger->measurement->exposedDays)->toBeGreaterThan(0);
    expect($trigger->measurement->occurrences)->toBeGreaterThan(0);
});

it('leaves inert tags below their own noise band', function () use ($today): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 120, seed: 2026))
        ->tag('trigger', rate: 0.22, effect: 3.0)
        ->tag('noise-a', rate: 0.30)
        ->tag('noise-b', rate: 0.25)
        ->tag('noise-c', rate: 0.20)
        ->tag('noise-d', rate: 0.15)
        ->tag('noise-e', rate: 0.28)
        ->plant($user, $condition, $today);

    $clearing = collect(app(ComputeCorrelations::class)($user, $condition)->suspects())
        ->filter(static fn (CorrelationSuspect $suspect): bool => $suspect->clearsNoiseBand)
        ->flatMap(suspectSlugs(...))
        ->all();

    expect($clearing)->toContain('trigger');
    expect(count($clearing))->toBeLessThanOrEqual(2);
});

it('returns the whole lag profile with a sample size on every point', function () use ($today): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 120, seed: 2026))
        ->tag('trigger', rate: 0.22, effect: 3.0)
        ->tag('noise-a', rate: 0.30)
        ->tag('noise-b', rate: 0.25)
        ->plant($user, $condition, $today);

    $report = app(ComputeCorrelations::class)($user, $condition);

    expect($report->windowDays)->toBe(CorrelationThresholds::EXPOSURE_WINDOW_DAYS);

    foreach ($report->suspects() as $suspect) {
        expect($suspect->lagProfile->points)->toHaveCount(CorrelationThresholds::LAG_PROFILE_DAYS + 1);

        foreach ($suspect->lagProfile->points as $point) {
            expect($point->days)->toBeGreaterThan(0);
        }

        expect($suspect->measurement->exposedDays)
            ->toBeGreaterThanOrEqual(CorrelationThresholds::MINIMUM_EXPOSED_DAYS);
        expect($suspect->measurement->baselineDays)
            ->toBeGreaterThanOrEqual(CorrelationThresholds::MINIMUM_BASELINE_DAYS);
    }
});

it('never accuses an innocent tag that only ever travels with a real trigger', function () use ($today): void {
    $user = User::factory()->createQuietly(['timezone' => 'Europe/London']);
    $condition = Condition::factory()->for($user)->createQuietly();

    (new SyntheticJournal(days: 150, seed: 36))
        ->tag('dairy', rate: 0.08, effect: 3.0)
        ->tag('sugar', rate: 0.08)
        ->tag('noise-a', rate: 0.25)
        ->tag('noise-b', rate: 0.20)
        ->coOccur('dairy', 'sugar', strength: 0.30)
        ->plant($user, $condition, $today);

    $suspects = app(ComputeCorrelations::class)($user, $condition)->suspects();

    $singleTagged = collect($suspects)
        ->filter(static fn (CorrelationSuspect $suspect): bool => $suspect->granularity->isSingleTag())
        ->flatMap(suspectSlugs(...))
        ->all();

    expect($singleTagged)->not->toContain('sugar');
    expect($singleTagged)->not->toContain('dairy');

    $cluster = collect($suspects)->firstOrFail(
        static fn (CorrelationSuspect $suspect): bool => $suspect->granularity->isCluster(),
    );

    expect(suspectSlugs($cluster))->toContain('dairy');
    expect(suspectSlugs($cluster))->toContain('sugar');
    expect($cluster->clearsNoiseBand)->toBeTrue();
    expect($cluster->measurement->exposedDays)->toBeGreaterThan(0);
    expect($cluster->measurement->occurrences)->toBeGreaterThan(0);
    expect($cluster->toArray()['granularity'])->toBe('co_occurrence_cluster');
});

it('refuses to correlate a condition the user does not own', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->createQuietly();

    expect(fn () => app(ComputeCorrelations::class)($user, $condition))
        ->toThrow(ConditionNotOwnedException::class);
});
