<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A trigger category and the number of days it turned up in the window.
 *
 * Purely descriptive: a count of what the user ate, with no comparison against
 * anything. It says "you had dairy on 18 of the last 30 days" and stops there —
 * the moment a count is set beside a condition it stops being a description and
 * starts being a claim, which is what the 90-day gate exists to prevent.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class LoggedTag implements Arrayable
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $days,
    ) {}

    /**
     * @return array{name: string, slug: string, days: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'days' => $this->days,
        ];
    }
}
