<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A month's worth of calendar cells, plus the neighbours the month nav pans to.
 *
 * `leadingBlanks` is how many empty cells precede the 1st so the grid lines up
 * under a Monday-first weekday header. Weeks are ISO (Monday–Sunday).
 *
 * A neighbour is null when the journal does not reach it, and the nav drops the
 * control rather than offering a month whose every day would be refused.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class CalendarMonth implements Arrayable
{
    /**
     * @param  array<int, CalendarDay>  $days
     */
    public function __construct(
        public string $month,
        public string $label,
        public ?string $previousMonth,
        public ?string $nextMonth,
        public int $leadingBlanks,
        public array $days,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     previousMonth: string|null,
     *     nextMonth: string|null,
     *     leadingBlanks: int,
     *     days: array<int, array{date: string, level: int, hasCheckin: bool, isToday: bool, isReachable: bool}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'label' => $this->label,
            'previousMonth' => $this->previousMonth,
            'nextMonth' => $this->nextMonth,
            'leadingBlanks' => $this->leadingBlanks,
            'days' => array_map(
                static fn (CalendarDay $day): array => $day->toArray(),
                $this->days,
            ),
        ];
    }
}
