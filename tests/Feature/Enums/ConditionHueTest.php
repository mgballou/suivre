<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\ColorScheme;
use App\Enums\ConditionHue;
use App\Enums\RampStep;
use Tests\Support\Stylesheet;
use Tests\Support\Wcag;

/**
 * The curated hues are validated, not inspected. D20 carried two petrol defects
 * forward precisely because the ramp had been approved by eye, and every hue
 * here was generated against a luminance profile so this file can prove the
 * result instead of asserting it.
 */
dataset('ramps', function (): iterable {
    foreach (ConditionHue::cases() as $hue) {
        foreach (ColorScheme::ordered() as $scheme) {
            yield "{$hue->value} ({$scheme->value})" => [$hue, $scheme];
        }
    }
});

dataset('schemes', function (): iterable {
    foreach (ColorScheme::ordered() as $scheme) {
        yield $scheme->value => [$scheme];
    }
});

it('carries AA-legible ink on every step of every curated hue', function (ConditionHue $hue, ColorScheme $scheme): void {
    foreach (RampStep::cases() as $step) {
        $swatch = $hue->ramp($scheme)[$step->value];

        expect(Wcag::contrast($swatch, $step->ink($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "{$hue->value} step {$step->value} ({$scheme->value})");
    }
})->with('ramps');

it('carries AA-legible ink on every step of the app petrol ramp', function (ColorScheme $scheme): void {
    foreach (RampStep::cases() as $step) {
        expect(Wcag::contrast($step->petrol($scheme), $step->ink($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "petrol step {$step->value} ({$scheme->value})");
    }
})->with('schemes');

it('climbs monotonically in the direction its scheme climbs', function (ConditionHue $hue, ColorScheme $scheme): void {
    $luminances = array_map(Wcag::luminance(...), $hue->ramp($scheme));

    $ordered = $luminances;
    $scheme->isLight() ? rsort($ordered) : sort($ordered);

    expect($luminances)->toBe($ordered);
})->with('ramps');

it('keeps every curated hue out of the red band', function (ConditionHue $hue): void {
    expect($hue->angle() < 12.0 || $hue->angle() > 50.0)->toBeTrue();
})->with(ConditionHue::cases());

it('holds each ramp to the hue it declares', function (ConditionHue $hue, ColorScheme $scheme): void {
    // Steps 0 and 1 are near-neutral by design, so their measured angle is noise.
    foreach (array_slice($hue->ramp($scheme), 2) as $swatch) {
        expect(abs(Wcag::hueAngle($swatch) - $hue->angle()))->toBeLessThan(4.0);
    }
})->with('ramps');

it('offers no hue that collides with the app petrol ramp', function (ConditionHue $hue): void {
    $petrol = Wcag::hueAngle(RampStep::Severe->petrol(ColorScheme::Light));

    expect(abs($hue->angle() - $petrol))->toBeGreaterThan(30.0);
})->with(ConditionHue::cases());

it('ships the curated ramps the stylesheet renders', function (ConditionHue $hue, ColorScheme $scheme): void {
    $selector = $scheme->isLight()
        ? "[data-hue='{$hue->value}']"
        : ".dark [data-hue='{$hue->value}']";

    foreach ($hue->ramp($scheme) as $step => $swatch) {
        expect(Stylesheet::hex($selector, "--condition-{$step}"))->toBe($swatch);
    }
})->with('ramps');

it('ships the petrol ramp and the ink rule the stylesheet renders', function (ColorScheme $scheme): void {
    $selector = $scheme->isLight() ? ':root' : '.dark';

    foreach (RampStep::cases() as $step) {
        expect(Stylesheet::hex($selector, "--intensity-{$step->value}"))->toBe($step->petrol($scheme));
        expect(Stylesheet::hex($selector, "--ramp-ink-{$step->value}"))->toBe($step->ink($scheme));
    }
})->with('schemes');
