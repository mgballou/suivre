<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Enums\RampStep;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One column of the exposure timeline: a day, and where it sat on the ramp.
 *
 * The step comes from `RampStep::fromRating()` rather than the raw rating, so
 * the timeline climbs the same scale as the calendar and the heatmap. The client
 * never buckets a rating itself — a second copy of the scale would drift.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class TimelineDay implements Arrayable
{
    public function __construct(
        public string $date,
        public string $label,
        public RampStep $level,
    ) {}

    /**
     * @return array{date: string, label: string, level: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'label' => $this->label,
            'level' => $this->level->value,
        ];
    }
}
