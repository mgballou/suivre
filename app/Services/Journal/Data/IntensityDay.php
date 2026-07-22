<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use App\Enums\RampStep;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One heatmap cell — a date and the ramp step it sits at.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class IntensityDay implements Arrayable
{
    public function __construct(
        public string $date,
        public RampStep $level,
    ) {}

    /**
     * @return array{date: string, level: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'level' => $this->level->value,
        ];
    }
}
