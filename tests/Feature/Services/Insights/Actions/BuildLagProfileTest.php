<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\BuildLagProfile;
use App\Services\Insights\Data\DayMask;

it('peaks at the lag the effect was planted on', function (): void {
    $intensities = array_fill(0, 50, 0);
    $occurrences = range(0, 49, 10);

    foreach ($occurrences as $occurrence) {
        $intensities[$occurrence + 2] = 8;
    }

    $profile = app(BuildLagProfile::class)($intensities, DayMask::of(50, $occurrences), 5);

    expect($profile->peakLag())->toBe(2);
});

it('returns a point per lag out to the requested horizon, each with its own n', function (): void {
    $intensities = array_fill(0, 40, 1);
    $presence = DayMask::of(40, [0, 10, 20]);

    $profile = app(BuildLagProfile::class)($intensities, $presence, 7);

    expect($profile->points)->toHaveCount(8);
    expect($profile->points[0]->lag)->toBe(0);
    expect($profile->points[7]->lag)->toBe(7);

    foreach ($profile->points as $point) {
        expect($point->days)->toBe(3);
    }
});

it('counts only the occurrences that still have a rated day at that distance', function (): void {
    $intensities = array_fill(0, 12, 2);
    $intensities[11] = null;
    $presence = DayMask::of(12, [0, 9]);

    $profile = app(BuildLagProfile::class)($intensities, $presence, 3);

    expect($profile->points[0]->days)->toBe(2);
    expect($profile->points[2]->days)->toBe(1);
    expect($profile->points[3]->days)->toBe(1);
});

it('breaks the profile where no occurrence reaches, rather than drawing a zero', function (): void {
    $intensities = array_fill(0, 10, 3);
    $presence = DayMask::of(10, [9]);

    $profile = app(BuildLagProfile::class)($intensities, $presence, 3);

    expect($profile->points[0]->lift)->not->toBeNull();
    expect($profile->points[1]->lift)->toBeNull();
    expect($profile->points[1]->days)->toBe(0);
});
