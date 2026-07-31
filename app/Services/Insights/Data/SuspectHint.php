<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Enums\AttributionGranularity;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One ranked suspect, flattened for the insights surface.
 *
 * Everything the reader needs to discount the row travels with it, because the
 * row is not trustworthy on its own: SUI-36 found that on a representative
 * ninety-day draw the top two ranked tags were pure noise and the real triggers
 * landed third and fourth. So `exposedDays` and `baselineDays` are carried, not
 * summarised; `clearsNoiseBand` says whether the lift beat what the same tag
 * produces when its days are shuffled away from the ratings; and `granularity`
 * decides whether the surface may name a tag at all or must speak about the
 * pattern it sits in (D24).
 *
 * `lift` is in rating points on the 0–10 scale — the difference between the mean
 * on exposed days and the mean on days without. Not a percentage, and not a
 * probability.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class SuspectHint implements Arrayable
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public array $tags,
        public AttributionGranularity $granularity,
        public float $lift,
        public int $exposedDays,
        public int $baselineDays,
        public ?int $peakLag,
        public bool $clearsNoiseBand,
    ) {}

    /**
     * @return array{
     *     tags: array<int, string>,
     *     granularity: string,
     *     lift: float,
     *     exposedDays: int,
     *     baselineDays: int,
     *     peakLag: int|null,
     *     clearsNoiseBand: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'tags' => $this->tags,
            'granularity' => $this->granularity->value,
            'lift' => round($this->lift, 1),
            'exposedDays' => $this->exposedDays,
            'baselineDays' => $this->baselineDays,
            'peakLag' => $this->peakLag,
            'clearsNoiseBand' => $this->clearsNoiseBand,
        ];
    }

    public static function fromSuspect(CorrelationSuspect $suspect): self
    {
        return new self(
            tags: array_map(
                static fn (SuspectTag $tag): string => $tag->name,
                $suspect->tags,
            ),
            granularity: $suspect->granularity,
            lift: $suspect->measurement->lift,
            exposedDays: $suspect->measurement->exposedDays,
            baselineDays: $suspect->measurement->baselineDays,
            peakLag: $suspect->lagProfile->peakLag(),
            clearsNoiseBand: $suspect->clearsNoiseBand,
        );
    }
}
