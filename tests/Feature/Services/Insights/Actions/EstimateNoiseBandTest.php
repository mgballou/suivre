<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\EstimateNoiseBand;
use App\Services\Insights\Data\DayMask;

it('draws a band a genuinely aligned tag clears', function (): void {
    $length = 120;
    $occurrences = range(0, 119, 6);
    $intensities = array_fill(0, $length, 1);

    foreach ($occurrences as $occurrence) {
        for ($lag = 0; $lag <= 2; $lag++) {
            if ($occurrence + $lag < $length) {
                $intensities[$occurrence + $lag] = 9;
            }
        }
    }

    $presence = DayMask::of($length, $occurrences);
    $band = app(EstimateNoiseBand::class)($intensities, $presence, 2);

    expect($band)->not->toBeNull();
    expect($band)->toBeLessThan(8.0);
});

it('draws a band an unaligned tag does not clear', function (): void {
    $length = 120;
    $intensities = array_fill(0, $length, 1);

    for ($day = 0; $day < $length; $day += 7) {
        $intensities[$day] = 9;
    }

    $presence = DayMask::of($length, range(1, 119, 5));
    $band = app(EstimateNoiseBand::class)($intensities, $presence, 2);

    expect($band)->not->toBeNull();
    expect($band)->toBeGreaterThan(0.0);
});

it('refuses to draw a band from a span too short to rotate', function (): void {
    $band = app(EstimateNoiseBand::class)(array_fill(0, 6, 3), DayMask::of(6, [0, 3]), 3);

    expect($band)->toBeNull();
});
