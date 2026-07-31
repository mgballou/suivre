<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\CheckTagsAreSeparable;
use App\Services\Insights\Data\DayMask;

it('separates a tag whose lift survives on the days its partner is absent', function (): void {
    $length = 60;
    $subjectDays = range(0, 59, 2);
    $partnerDays = range(0, 19, 2);

    $intensities = array_fill(0, $length, 0);

    foreach ($subjectDays as $day) {
        $intensities[$day] = 6;
    }

    $separable = app(CheckTagsAreSeparable::class)(
        $intensities,
        DayMask::of($length, $subjectDays),
        DayMask::of($length, $partnerDays),
        marginalLift: 6.0,
        windowDays: 0,
    );

    expect($separable)->toBeTrue();
});

it('refuses to separate a tag whose lift collapses once its partner is stratified out', function (): void {
    $length = 60;
    $subjectDays = range(0, 59, 2);
    $partnerDays = range(0, 19, 2);

    $intensities = array_fill(0, $length, 0);

    foreach ($partnerDays as $day) {
        $intensities[$day] = 8;
    }

    $separable = app(CheckTagsAreSeparable::class)(
        $intensities,
        DayMask::of($length, $subjectDays),
        DayMask::of($length, $partnerDays),
        marginalLift: 2.6,
        windowDays: 0,
    );

    expect($separable)->toBeFalse();
});

it('refuses to separate a tag that never appears without its partner', function (): void {
    $length = 60;
    $days = range(0, 59, 2);

    $intensities = array_fill(0, $length, 0);

    foreach ($days as $day) {
        $intensities[$day] = 5;
    }

    $separable = app(CheckTagsAreSeparable::class)(
        $intensities,
        DayMask::of($length, $days),
        DayMask::of($length, $days),
        marginalLift: 5.0,
        windowDays: 0,
    );

    expect($separable)->toBeFalse();
});

it('has nothing to defend when the marginal lift is not positive', function (): void {
    $separable = app(CheckTagsAreSeparable::class)(
        array_fill(0, 20, 3),
        DayMask::of(20, [0, 1]),
        DayMask::of(20, [0, 1]),
        marginalLift: -0.4,
        windowDays: 0,
    );

    expect($separable)->toBeTrue();
});
