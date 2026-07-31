<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Condition intensity and tag exposure over the same run of days, on one axis.
 *
 * This is the honest counterweight to the ranked suspects. SUI-36 showed that
 * marginal lift over-accuses foods that travel with a real trigger, and no
 * amount of data at personal scale pulls them apart. Letting the reader *see*
 * two tags marking the same columns is the most truthful correlation surface
 * available here: it does not resolve the confound, it shows it, which is the
 * one thing a ranking cannot do.
 *
 * Each tag carries a `present` list index-aligned to `days`. Sending dates and
 * asking the client to align them would make a rendering bug possible; aligning
 * server-side makes the two series impossible to offset.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ExposureTimeline implements Arrayable
{
    /**
     * @param  array<int, TimelineDay>  $days
     * @param  array<int, TimelineTag>  $tags
     */
    public function __construct(
        public array $days,
        public array $tags,
        public int $rangeDays,
    ) {}

    /**
     * @return array{
     *     days: array<int, array<string, mixed>>,
     *     tags: array<int, array<string, mixed>>,
     *     rangeDays: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'days' => array_map(
                static fn (TimelineDay $day): array => $day->toArray(),
                $this->days,
            ),
            'tags' => array_map(
                static fn (TimelineTag $tag): array => $tag->toArray(),
                $this->tags,
            ),
            'rangeDays' => $this->rangeDays,
        ];
    }
}
