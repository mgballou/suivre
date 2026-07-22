<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One day's check-in surface: the saved values, the scales they were chosen
 * from, and the ramp step the day currently reads at.
 *
 * `level` follows the same convention as CalendarDay — 0 means "no entry",
 * 1–5 climb the petrol ramp (D20). Until SUI-8 surfaces condition intensity a
 * logged day sits at step 1, so this is the value the day's colour arrives on.
 *
 * `month` is carried so the return link lands on the month the day belongs to
 * rather than the user's current month.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DayView implements Arrayable
{
    /**
     * @param  array<string, array<int, ScaleOption>>  $scales
     */
    public function __construct(
        public string $date,
        public string $label,
        public string $month,
        public int $level,
        public bool $isToday,
        public ?int $mood,
        public ?int $sleep,
        public ?int $stress,
        public ?string $note,
        public array $scales,
    ) {}

    /**
     * @return array{
     *     date: string,
     *     label: string,
     *     month: string,
     *     level: int,
     *     isToday: bool,
     *     checkin: array{mood: int|null, sleep: int|null, stress: int|null, note: string|null},
     *     scales: array<string, array<int, array{value: int, label: string}>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'label' => $this->label,
            'month' => $this->month,
            'level' => $this->level,
            'isToday' => $this->isToday,
            'checkin' => [
                'mood' => $this->mood,
                'sleep' => $this->sleep,
                'stress' => $this->stress,
                'note' => $this->note,
            ],
            'scales' => array_map(
                static fn (array $options): array => array_map(
                    static fn (ScaleOption $option): array => $option->toArray(),
                    $options,
                ),
                $this->scales,
            ),
        ];
    }
}
