<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The descriptive half of the insights surface: what the user logged, and how
 * far each condition is from the point where correlation can honestly speak.
 *
 * This is not a placeholder for the ranking. The SUI-36 spike put the earliest
 * trustworthy ranking at around 90 days, which leaves three months where a
 * faithful logger would otherwise get nothing back — the most likely point of
 * abandonment in the product. Descriptive self-tracking is the answer to that,
 * and it stays on the page after the ranking arrives rather than being replaced
 * by it: a ranking is added to what the user already had, never swapped for it.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class JournalSummary implements Arrayable
{
    /**
     * @param  array<int, ConditionReadiness>  $conditions
     * @param  array<int, LoggedTag>  $tags
     */
    public function __construct(
        public array $conditions,
        public array $tags,
    ) {}

    /**
     * @return array{
     *     conditions: array<int, array<string, mixed>>,
     *     tags: array<int, array<string, mixed>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'conditions' => array_map(
                static fn (ConditionReadiness $condition): array => $condition->toArray(),
                $this->conditions,
            ),
            'tags' => array_map(
                static fn (LoggedTag $tag): array => $tag->toArray(),
                $this->tags,
            ),
        ];
    }
}
