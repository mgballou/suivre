<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\RampStep;

it('buckets a null or zero rating onto the unlogged step', function (?int $rating): void {
    expect(RampStep::fromRating($rating))->toBe(RampStep::None);
})->with([
    'no log at all' => [null],
    'a logged zero' => [0],
]);

it('buckets the 0–10 rating scale across the ramp', function (int $rating, RampStep $step): void {
    expect(RampStep::fromRating($rating))->toBe($step);
})->with([
    '1' => [1, RampStep::Barely],
    '2' => [2, RampStep::Barely],
    '3' => [3, RampStep::Mild],
    '4' => [4, RampStep::Mild],
    '5' => [5, RampStep::Moderate],
    '6' => [6, RampStep::Moderate],
    '7' => [7, RampStep::Strong],
    '8' => [8, RampStep::Strong],
    '9' => [9, RampStep::Severe],
    '10' => [10, RampStep::Severe],
]);

it('caps a rating above the scale at the top step', function (): void {
    expect(RampStep::fromRating(99))->toBe(RampStep::Severe);
});

it('knows which steps are logged', function (): void {
    expect(RampStep::None->isLogged())->toBeFalse();
    expect(RampStep::Barely->isLogged())->toBeTrue();
    expect(RampStep::Severe->isLogged())->toBeTrue();
});

it('reads its significant grouping from the enum, never a call site', function (RampStep $step, bool $significant): void {
    expect($step->isSignificant())->toBe($significant);
})->with([
    'none' => [RampStep::None, false],
    'barely' => [RampStep::Barely, false],
    'mild' => [RampStep::Mild, false],
    'moderate' => [RampStep::Moderate, true],
    'strong' => [RampStep::Strong, true],
    'severe' => [RampStep::Severe, true],
]);

it('has six steps spanning 0 to 5', function (): void {
    expect(RampStep::cases())->toHaveCount(6);
    expect(RampStep::None->value)->toBe(0);
    expect(RampStep::Severe->value)->toBe(5);
});
