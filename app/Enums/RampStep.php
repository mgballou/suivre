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
