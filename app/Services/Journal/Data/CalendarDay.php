<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One cell of the month grid.
 *
 * `level` is the petrol ramp step (D20): 0 means "no entry", 1–5 climb the
 * ramp. It reads the day's *worst* condition rating, so a calm condition can
 * never mask a severe one; a day checked into but never rated sits at step 1,
 * and `hasCheckin` carries the marker independently of the colour.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class CalendarDay implements Arrayable
{
    public function __construct(
        public string $date,
        public int $level,
        public bool $hasCheckin,
        public bool $isToday,
    ) {}

    /**
     * @return array{date: string, level: int, hasCheckin: bool, isToday: bool}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'level' => $this->level,
            'hasCheckin' => $this->hasCheckin,
            'isToday' => $this->isToday,
        ];
    }
}
