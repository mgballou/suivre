<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One day's journal surface: the saved check-in, the conditions tracked that
 * day with their ratings, the flares logged against it, and the scales each was
 * chosen from.
 *
 * `level` follows the same convention as CalendarDay — 0 means "no entry", 1–5
 * climb the petrol ramp (D20). It reads the day's *worst* condition rating, so
 * a calm condition can never mask a severe one; a day with a check-in but no
 * rating still sits at step 1, which is what makes the colour arrive at all.
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
     * @param  array<int, DayCondition>  $conditions
     * @param  array<int, DayFlare>  $flares
     * @param  array<int, ScaleOption>  $flareIntensities
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
        public array $conditions,
        public array $flares,
        public array $flareIntensities,
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
     *     conditions: array<int, array{id: int, name: string, hue: string, intensity: int|null, level: int}>,
     *     flares: array<int, array{id: int, conditionName: string, hue: string, intensity: string, time: string, duration: string|null, note: string|null}>,
     *     flareIntensities: array<int, array{value: int, label: string}>,
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
            'conditions' => array_map(
                static fn (DayCondition $condition): array => $condition->toArray(),
                $this->conditions,
            ),
            'flares' => array_map(
                static fn (DayFlare $flare): array => $flare->toArray(),
                $this->flares,
            ),
            'flareIntensities' => array_map(
                static fn (ScaleOption $option): array => $option->toArray(),
                $this->flareIntensities,
            ),
        ];
    }
}
