<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The lift observed exactly `lag` days after a tag occurred, against the same
 * tag-free baseline every other lag is measured against.
 *
 * `days` is the number of occurrences that had a rated day at this distance —
 * the point's own sample size. A null `lift` means none did, and the profile
 * breaks there rather than drawing a zero.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class LagPoint implements Arrayable
{
    public function __construct(
        public int $lag,
        public ?float $lift,
        public int $days,
    ) {}

    /**
     * @return array{lag: int, lift: float|null, days: int}
     */
    public function toArray(): array
    {
        return [
            'lag' => $this->lag,
            'lift' => $this->lift,
            'days' => $this->days,
        ];
    }
}
