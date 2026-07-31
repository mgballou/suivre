<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The curated set a condition's colour is chosen from (D20).
 *
 * Colour is *chosen, not picked*: a free picker breaks ramp construction and
 * dark-mode contrast, so seven hues ship with a pre-built five-step ramp each,
 * authored twice — light climbing by getting darker, dark by getting lighter,
 * never a lightness flip. Petrol is reserved for the application itself and is
 * absent here.
 *
 * Each ramp is generated to petrol's per-step **relative luminance**, not to a
 * per-hue eyeball: contrast is a pure function of luminance, so matching the
 * profile gives every hue petrol's contrast behaviour exactly and lets one ink
 * rule (RampStep::ink) serve all of them. ConditionHueTest proves it rather
 * than asserting it.
 *
 * No hue sits in the red band. D20 rules out a diverging good→bad scale, and
 * red on a heatmap of your own health is the specific thing being avoided.
 */
enum ConditionHue: string implements HasColor, HasLabel
{
    use HasOrderedCases;

    case Clay = 'clay';
    case Ochre = 'ochre';
    case Moss = 'moss';
    case Marine = 'marine';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Plum = 'plum';

    public function getLabel(): string
    {
        return match ($this) {
            self::Clay => 'Clay',
            self::Ochre => 'Ochre',
            self::Moss => 'Moss',
            self::Marine => 'Marine',
            self::Indigo => 'Indigo',
            self::Violet => 'Violet',
            self::Plum => 'Plum',
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return Color::hex($this->swatch(ColorScheme::Light));
    }

    /**
     * The hue's OKLCH angle, in degrees — the axis the ramp is generated along
     * and the value the no-red assertion is made against.
     */
    public function angle(): float
    {
        return match ($this) {
            self::Clay => 62.0,
            self::Ochre => 95.0,
            self::Moss => 140.0,
            self::Marine => 230.0,
            self::Indigo => 268.0,
            self::Violet => 305.0,
            self::Plum => 340.0,
        };
    }

    /**
     * The hue's six steps, indexed by RampStep value. Step 0 is deliberately
     * near-neutral in every hue: an unrated condition should read as quiet, not
     * as a faint version of itself.
     *
     * @return array<int, string>
     */
    public function ramp(ColorScheme $scheme): array
    {
        return $scheme->isLight()
            ? $this->lightRamp()
            : $this->darkRamp();
    }

    /**
     * The hue's identity colour — the step the swatch dot and the Filament
     * colour column read, chosen because it carries the most chroma.
     */
    public function swatch(ColorScheme $scheme): string
    {
        return $this->ramp($scheme)[RampStep::Severe->value];
    }

    public function isWarm(): bool
    {
        return in_array($this, self::warm(), strict: true);
    }

    public function isCool(): bool
    {
        return in_array($this, self::cool(), strict: true);
    }

    /**
     * @return array<int, string>
     */
    private function lightRamp(): array
    {
        return match ($this) {
            self::Clay => ['#f2f0ef', '#f3eae4', '#e5d2c2', '#d0b29a', '#b49071', '#906c4c'],
            self::Ochre => ['#f1f0ef', '#eeebe2', '#dbd6bf', '#c1b795', '#a2966b', '#7e7346'],
            self::Moss => ['#f0f1ef', '#e7ede5', '#cadac6', '#a6bea1', '#819e7b', '#5d7a57'],
            self::Marine => ['#eff1f1', '#e4edf3', '#c1d9e5', '#97bdd0', '#6e9eb5', '#487a91'],
            self::Indigo => ['#f0f1f2', '#e8ebf5', '#cdd5eb', '#aab7d8', '#8796be', '#63739a'],
            self::Violet => ['#f1f1f2', '#edeaf3', '#dbd1e7', '#c0b1d2', '#a18fb8', '#7e6b94'],
            self::Plum => ['#f2f1f1', '#f3e9ef', '#e6cfdd', '#cfaec3', '#b48aa5', '#906781'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function darkRamp(): array
    {
        return match ($this) {
            self::Clay => ['#201d1b', '#342921', '#4f3e2f', '#705842', '#9d7c60', '#bb9474'],
            self::Ochre => ['#1f1e1b', '#2e2b1f', '#47412d', '#645c3e', '#8d825b', '#a89c6e'],
            self::Moss => ['#1c1e1c', '#252e23', '#374534', '#4d6249', '#6f8969', '#85a47e'],
            self::Marine => ['#1b1e20', '#202d34', '#2e4550', '#406171', '#5d899e', '#71a3bc'],
            self::Indigo => ['#1d1f21', '#272b37', '#3a4154', '#515c77', '#7483a7', '#8b9bc6'],
            self::Violet => ['#1f1e20', '#2e2a35', '#463e51', '#635773', '#8c7ba0', '#a894bf'],
            self::Plum => ['#211e1f', '#33282f', '#4f3c48', '#705366', '#9d7890', '#bb8fac'],
        };
    }

    /**
     * The warm half of the wheel — the picker groups the swatches this way so
     * a user scanning for "a warm one" is not reading seven names.
     *
     * @return array<int, self>
     */
    public static function warm(): array
    {
        return [
            self::Clay,
            self::Ochre,
            self::Moss,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function cool(): array
    {
        return [
            self::Marine,
            self::Indigo,
            self::Violet,
            self::Plum,
        ];
    }

    /**
     * The first hue not already spoken for, so a second condition never lands
     * on the same colour as the first. Falls back to the first case once every
     * hue is in use — seven is a soft cap on distinguishable colours, not on
     * how many conditions a user may track.
     *
     * @param  array<int, self>  $taken
     */
    public static function firstUnused(array $taken): self
    {
        foreach (self::cases() as $hue) {
            if (! in_array($hue, $taken, strict: true)) {
                return $hue;
            }
        }

        return self::cases()[0];
    }
}
