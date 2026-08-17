<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use App\Enums\ColorScheme;
use Tests\Support\Material;
use Tests\Support\Stylesheet;
use Tests\Support\Wcag;

dataset('schemes', function (): iterable {
    foreach (ColorScheme::ordered() as $scheme) {
        yield $scheme->value => [$scheme];
    }
});

function selector(ColorScheme $scheme): string
{
    return $scheme->isLight() ? ':root' : '.dark';
}

it('ships the surface tokens the stylesheet renders', function (ColorScheme $scheme): void {
    foreach (Material::surfaces($scheme) as $token => $hex) {
        expect(Stylesheet::hex(selector($scheme), "--{$token}"))->toBe($hex);
    }
})->with('schemes');

it('keeps the page surface identical to the scheme the ramps are built against', function (ColorScheme $scheme): void {
    expect(Material::surfaces($scheme)['surface-page'])->toBe($scheme->background());
})->with('schemes');

it('declares a shadow for each elevation step above the page', function (ColorScheme $scheme): void {
    expect(Stylesheet::raw(selector($scheme), '--shadow-raised'))->not->toBe('');
    expect(Stylesheet::raw(selector($scheme), '--shadow-floating'))->not->toBe('');
})->with('schemes');

it('declares the glass alpha and blur once, outside either scheme', function (): void {
    expect(Stylesheet::raw(':root', '--glass-alpha'))->toBe((string) Material::GLASS_ALPHA);
    expect(Stylesheet::raw(':root', '--glass-blur'))->toBe(Material::GLASS_BLUR);
});

it('carries AA-legible ink on every opaque surface', function (ColorScheme $scheme): void {
    foreach (['surface-page', 'surface-raised', 'surface-floating', 'panel-tint', 'tab-indicator'] as $token) {
        $surface = Material::surfaces($scheme)[$token];

        expect(Wcag::contrast($surface, Material::ink($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "{$token} ink ({$scheme->value})");
        expect(Wcag::contrast($surface, Material::mutedInk($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "{$token} muted ink ({$scheme->value})");
    }
})->with('schemes');

it('carries AA-legible ink on glass over the worst backdrop it can sit above', function (ColorScheme $scheme): void {
    $rendered = Wcag::composite(
        Material::surfaces($scheme)['glass-surface'],
        Material::worstBackdrop($scheme),
        Material::GLASS_ALPHA,
    );

    expect(Wcag::contrast($rendered, Material::ink($scheme)))
        ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "glass ink ({$scheme->value})");
    expect(Wcag::contrast($rendered, Material::mutedInk($scheme)))
        ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "glass muted ink ({$scheme->value})");
})->with('schemes');

it('lifts each elevation step above the one below it', function (ColorScheme $scheme): void {
    $surfaces = Material::surfaces($scheme);

    $page = Wcag::luminance($surfaces['surface-page']);
    $raised = Wcag::luminance($surfaces['surface-raised']);
    $floating = Wcag::luminance($surfaces['surface-floating']);

    $scheme->isLight()
        ? expect($raised)->toBeGreaterThanOrEqual($page)
        : expect($raised)->toBeGreaterThan($page);

    expect($floating)->toBeGreaterThanOrEqual($raised);
})->with('schemes');

it('falls back to the floating surface where backdrop-filter is unsupported', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@supports not (backdrop-filter: blur(1px))');
});
