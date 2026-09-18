<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\Support\Wcag;

it('returns the top colour at full opacity', function (): void {
    expect(Wcag::composite('#3f7d7b', '#ffffff', 1.0))->toBe('#3f7d7b');
});

it('returns the backdrop at zero opacity', function (): void {
    expect(Wcag::composite('#3f7d7b', '#ffffff', 0.0))->toBe('#ffffff');
});

it('blends each channel linearly in sRGB space', function (): void {
    expect(Wcag::composite('#000000', '#ffffff', 0.5))->toBe('#808080');
});

it('rounds each channel rather than truncating it', function (): void {
    // 0.8 * 27 + 0.2 * 237 = 69.0 exactly; 0.8 * 29 + 0.2 * 238 = 70.8 -> 71.
    expect(Wcag::composite('#1b1c1d', '#edeeee', 0.8))->toBe('#454647');
});
