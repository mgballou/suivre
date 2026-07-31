<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\GroupCoOccurringTags;
use App\Services\Insights\Data\DayMask;

it('leaves tags that rarely coincide as separate suspects', function (): void {
    $length = 60;
    $intensities = array_fill(0, $length, 4);
    $presence = [
        7 => DayMask::of($length, range(0, 59, 4)),
        9 => DayMask::of($length, range(2, 59, 4)),
    ];

    $clusters = app(GroupCoOccurringTags::class)(
        $intensities,
        $presence,
        [7 => 2.0, 9 => 2.0],
        0,
    );

    expect($clusters->groups)->toBe([[7], [9]]);
});

it('merges tags whose lift cannot be told apart on the days they differ', function (): void {
    $length = 60;
    $shared = range(0, 59, 2);
    $intensities = array_fill(0, $length, 0);

    foreach ($shared as $day) {
        $intensities[$day] = 7;
    }

    $presence = [
        3 => DayMask::of($length, $shared),
        5 => DayMask::of($length, $shared),
    ];

    $clusters = app(GroupCoOccurringTags::class)(
        $intensities,
        $presence,
        [3 => 7.0, 5 => 7.0],
        0,
    );

    expect($clusters->groups)->toBe([[3, 5]]);
});

it('pulls a whole chain of co-travellers into one pattern', function (): void {
    $length = 60;
    $shared = range(0, 59, 2);
    $intensities = array_fill(0, $length, 0);

    foreach ($shared as $day) {
        $intensities[$day] = 7;
    }

    $presence = [
        1 => DayMask::of($length, $shared),
        2 => DayMask::of($length, $shared),
        4 => DayMask::of($length, $shared),
        8 => DayMask::of($length, range(1, 59, 2)),
    ];

    $clusters = app(GroupCoOccurringTags::class)(
        $intensities,
        $presence,
        [1 => 7.0, 2 => 7.0, 4 => 7.0, 8 => 0.0],
        0,
    );

    expect($clusters->groups)->toBe([[1, 2, 4], [8]]);
});
