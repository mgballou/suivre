<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * WCAG 2.x relative luminance and contrast, straight from the specification.
 *
 * The ramps this measures were generated against these formulas rather than
 * approved by eye — D20's two known petrol defects are what eyeballing cost.
 */
final class Wcag
{
    public const float AA_SMALL_TEXT = 4.5;

    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(self::linear(...), self::channels($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * The hue angle of a colour in OKLCH, in degrees. Used to prove no curated
     * hue sits in the red band.
     */
    public static function hueAngle(string $hex): float
    {
        [$red, $green, $blue] = array_map(self::linear(...), self::channels($hex));

        $long = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $medium = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $short = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        $a = 1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short;
        $b = 0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short;

        return fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);
    }

    /**
     * `$over` laid on `$under` at `$alpha` opacity — what a translucent surface
     * actually renders as. Contrast is measured against this, never against the
     * glass token's nominal colour, because a user reads the composite.
     */
    public static function composite(string $over, string $under, float $alpha): string
    {
        $top = self::bytes($over);
        $bottom = self::bytes($under);

        return sprintf(
            '#%02x%02x%02x',
            ...array_map(
                static fn (int $a, int $b): int => (int) round($alpha * $a + (1 - $alpha) * $b),
                $top,
                $bottom,
            ),
        );
    }

    /**
     * @return array<int, int>
     */
    private static function bytes(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    /**
     * @return array<int, float>
     */
    private static function channels(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [
            (($value >> 16) & 0xFF) / 255,
            (($value >> 8) & 0xFF) / 255,
            ($value & 0xFF) / 255,
        ];
    }

    private static function linear(float $value): float
    {
        return $value <= 0.04045
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    }
}
