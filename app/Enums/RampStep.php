<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A step on the petrol intensity ramp (D20) — the server-side half of the
 * scale the calendar and the heatmap render. Step 0 is an unlogged day; 1–5
 * climb.
 *
 * The ramp has six steps and a daily rating has eleven (0–10), so the bucketing
 * lives here rather than at each read site — one definition, so the calendar
 * and the heatmap cannot disagree about which rating sits at which step.
 */
enum RampStep: int
{
    case None = 0;
    case Barely = 1;
    case Mild = 2;
    case Moderate = 3;
    case Strong = 4;
    case Severe = 5;

    public function isLogged(): bool
    {
        return $this !== self::None;
    }

    /**
     * The app's own petrol swatch at this step (D20). Petrol is reserved for
     * Suivre itself and is never offered as a condition hue — ConditionHue
     * carries those.
     */
    public function petrol(ColorScheme $scheme): string
    {
        $ramp = $scheme->isLight()
            ? ['#eff1f1', '#e2eeee', '#bedbda', '#92c0bf', '#66a19f', '#3f7d7b']
            : ['#1b1f1f', '#1e2e2e', '#2a4646', '#3a6362', '#558c8a', '#68a7a5'];

        return $ramp[$this->value];
    }

    /**
     * The foreground a step's swatch can legibly carry.
     *
     * Every ramp in the app — petrol and all seven condition hues — is built to
     * one luminance profile per step, so the ink that clears WCAG AA is a
     * property of the *step*, not the hue. The threshold sits one step later in
     * light than in dark because the two ramps are authored independently: the
     * light ramp reaches ink-defeating darkness later than the dark ramp reaches
     * white-defeating lightness.
     */
    public function ink(ColorScheme $scheme): string
    {
        if ($scheme->isLight()) {
            return match ($this) {
                self::Severe => '#ffffff',
                default => '#16201f',
            };
        }

        return match ($this) {
            self::Strong, self::Severe => '#101917',
            default => '#edeeee',
        };
    }

    public function isSignificant(): bool
    {
        return in_array($this, self::significant(), strict: true);
    }

    /**
     * Bucket a 0–10 daily rating onto the ramp. A null rating (no log at all)
     * and a rating of 0 both mean "nothing recorded" and share step 0.
     */
    public static function fromRating(?int $rating): self
    {
        if ($rating === null || $rating <= 0) {
            return self::None;
        }

        return self::from(min((int) ceil($rating / 2), self::Severe->value));
    }

    /**
     * Steps that read as a notable day — the grouping insights treat as "bad".
     *
     * @return array<int, self>
     */
    public static function significant(): array
    {
        return [
            self::Moderate,
            self::Strong,
            self::Severe,
        ];
    }
}
