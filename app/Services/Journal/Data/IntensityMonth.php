<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A month of ramp steps for the calendar heatmap.
 *
 * Deliberately narrower than `CalendarMonth`: the heatmap is read-only, so it
 * carries no navigation targets and no "today" marker — only the date and the
 * step it sits at.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class IntensityMonth implements Arrayable
{
    /**
     * @param  array<int, IntensityDay>  $days
     */
    public function __construct(
        public string $month,
        public string $label,
        public int $leadingBlanks,
        public array $days,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     leadingBlanks: int,
     *     days: array<int, array{date: string, level: int}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'label' => $this->label,
            'leadingBlanks' => $this->leadingBlanks,
            'days' => array_map(
                static fn (IntensityDay $day): array => $day->toArray(),
                $this->days,
            ),
        ];
    }
}
