<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\FlareIntensity;

it('exposes a non-empty label, color and icon for every case', function (FlareIntensity $intensity): void {
    expect($intensity->getLabel())->not->toBe('');
    expect($intensity->getColor())->not->toBe('');
    expect($intensity->getIcon())->not->toBe('');
})->with(FlareIntensity::cases());

it('answers its single-state predicates', function (): void {
    expect(FlareIntensity::Mild->isMild())->toBeTrue();
    expect(FlareIntensity::Moderate->isModerate())->toBeTrue();
    expect(FlareIntensity::Severe->isSevere())->toBeTrue();
    expect(FlareIntensity::Mild->isSevere())->toBeFalse();
});

it('pairs the significant predicate with its set helper', function (): void {
    expect(FlareIntensity::significant())->toBe([FlareIntensity::Moderate, FlareIntensity::Severe]);
    expect(FlareIntensity::Moderate->isSignificant())->toBeTrue();
    expect(FlareIntensity::Severe->isSignificant())->toBeTrue();
    expect(FlareIntensity::Mild->isSignificant())->toBeFalse();
});

it('orders its cases by ascending severity', function (): void {
    expect(FlareIntensity::ordered())->toBe([
        FlareIntensity::Mild,
        FlareIntensity::Moderate,
        FlareIntensity::Severe,
    ]);
});
