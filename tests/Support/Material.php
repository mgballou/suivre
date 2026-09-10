<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ColorScheme;

/**
 * The material layer as authored in app.css.
 *
 * app.css is the source of truth; this is the assertion of what it must
 * contain, so a token cannot be edited in the stylesheet without a test
 * noticing. There is no App\Enums equivalent because no server code consumes an
 * elevation step — an enum here would be a domain primitive with no domain.
 */
final class Material
{
    /**
     * Set by the worst muted-ink case, not by taste. At 0.8 the composite over
     * body text measured 4.07:1 in light and 3.78:1 in dark against the quieter
     * ink — both short of AA. 0.88 is the first value that clears it in both
     * schemes with margin, and still blurs what passes beneath.
     */
    public const float GLASS_ALPHA = 0.88;

    public const string GLASS_BLUR = '12px';

    /** @return array<string, string> token name (without leading dashes) => hex */
    public static function surfaces(ColorScheme $scheme): array
    {
        return $scheme->isLight()
            ? [
                'surface-page' => '#fbfbfa',
                'surface-raised' => '#ffffff',
                'surface-floating' => '#ffffff',
                'panel-tint' => '#f6f7f6',
                'glass-surface' => '#fbfbfa',
                'glass-border' => '#e4e7e6',
                'tab-indicator' => '#e2eeee',
            ]
            : [
                'surface-page' => '#131314',
                'surface-raised' => '#1b1c1d',
                'surface-floating' => '#222324',
                'panel-tint' => '#191a1b',
                'glass-surface' => '#1b1c1d',
                'glass-border' => '#2a2e2e',
                'tab-indicator' => '#262a2a',
            ];
    }

    /** The ink a surface in this scheme carries. */
    public static function ink(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#16201f' : '#edeeee';
    }

    /** The quieter ink — labels, counts, the collapsed summary of a card. */
    public static function mutedInk(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#55625f' : '#9ba6a4';
    }

    /**
     * The worst thing that can pass under a glass surface. In light the ink is
     * dark, so the darkest backdrop is worst; in dark the ink is light, so the
     * lightest is. Body text is the extreme in both directions — a ramp swatch
     * never reaches it.
     */
    public static function worstBackdrop(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#16201f' : '#edeeee';
    }
}
