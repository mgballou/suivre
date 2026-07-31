<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\BuildExposureMask;
use App\Services\Insights\Actions\MeasureLift;
use App\Services\Insights\Data\DayMask;

/**
 * The numbers here are the spike's own (`tests/test_lag_lift.py`), on inputs
 * small enough to check by hand.
 */
it('is the difference of the two means, with both sample sizes', function (): void {
    $intensities = [0, 5, 0, 0, 0, 0];
    $presence = DayMask::of(6, [0]);
    $exposed = app(BuildExposureMask::class)($presence, 1);

    $measurement = app(MeasureLift::class)($intensities, $exposed, $exposed->complement(), $presence->count());

    expect($measurement)->not->toBeNull();
    expect($measurement->exposedDays)->toBe(2);
    expect($measurement->baselineDays)->toBe(4);
    expect($measurement->occurrences)->toBe(1);
    expect($measurement->exposedMean)->toBe(2.5);
    expect($measurement->baselineMean)->toBe(0.0);
    expect($measurement->lift)->toBe(2.5);
});

it('leaves unrated days out of both means rather than reading them as zero', function (): void {
    $intensities = [4, null, 4, 0, null, 0];

    $measurement = app(MeasureLift::class)(
        $intensities,
        DayMask::of(6, [0, 1, 2]),
        DayMask::of(6, [3, 4, 5]),
        3,
    );

    expect($measurement)->not->toBeNull();
    expect($measurement->exposedDays)->toBe(2);
    expect($measurement->baselineDays)->toBe(2);
    expect($measurement->lift)->toBe(4.0);
});

it('scales the lift by the pooled standard deviation', function (): void {
    $intensities = [6, 8, 2, 4];

    $measurement = app(MeasureLift::class)(
        $intensities,
        DayMask::of(4, [0, 1]),
        DayMask::of(4, [2, 3]),
        2,
    );

    expect($measurement)->not->toBeNull();
    expect($measurement->lift)->toBe(4.0);
    expect($measurement->cohensD)->toBeGreaterThan(2.8);
    expect($measurement->cohensD)->toBeLessThan(2.9);
});

it('states no lift at all when one side has no rated day', function (): void {
    $intensities = [null, null, 3, 3];

    $measurement = app(MeasureLift::class)(
        $intensities,
        DayMask::of(4, [0, 1]),
        DayMask::of(4, [2, 3]),
        2,
    );

    expect($measurement)->toBeNull();
});
