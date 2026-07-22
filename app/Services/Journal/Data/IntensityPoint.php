<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One point on the intensity trend.
 *
 * `label` is the pre-formatted x-axis tick: the client never derives a date,
 * because `new Date()` reads the device timezone rather than the user's. A null
 * `intensity` is an unlogged day and breaks the line rather than interpolating
 * across it — a gap is data, not zero.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class IntensityPoint implements Arrayable
{
    public function __construct(
        public string $date,
        public string $label,
        public ?int $intensity,
    ) {}

    /**
     * @return array{date: string, label: string, values: array{intensity: int|null}}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'label' => $this->label,
            'values' => ['intensity' => $this->intensity],
        ];
    }
}
