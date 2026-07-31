<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One row of the exposure timeline: a trigger category, and which of the
 * timeline's days it turned up on.
 *
 * `present` is index-aligned to the timeline's `days` — one boolean per column,
 * in order. `days` counts how many of them are true, so the row can state its
 * own frequency without the client summing an array to find out.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class TimelineTag implements Arrayable
{
    /**
     * @param  array<int, bool>  $present
     */
    public function __construct(
        public string $name,
        public string $slug,
        public array $present,
    ) {}

    public function days(): int
    {
        return count(array_filter($this->present));
    }

    /**
     * @return array{name: string, slug: string, present: array<int, bool>, days: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'present' => $this->present,
            'days' => $this->days(),
        ];
    }
}
