<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * How close one tracked condition is to having enough history for correlation
 * to be worth trusting.
 *
 * Readiness is per condition, not per user, because the engine's volume gate is:
 * `ComputeCorrelations` counts the days carrying a rating for **that** condition
 * and refuses to rank below `MINIMUM_LOGGED_DAYS`. Someone who added a second
 * condition in month three is genuinely two months behind on it, and a single
 * user-level number would promise them insights that will not arrive.
 *
 * `isReady` and `remainingDays` are computed here rather than in the component
 * for the same reason every threshold in this app is: the client must never hold
 * a second copy of a rule the engine owns.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ConditionReadiness implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $hue,
        public int $loggedDays,
        public int $requiredDays,
    ) {}

    public function isReady(): bool
    {
        return $this->loggedDays >= $this->requiredDays;
    }

    public function remainingDays(): int
    {
        return max(0, $this->requiredDays - $this->loggedDays);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     hue: string,
     *     loggedDays: int,
     *     requiredDays: int,
     *     remainingDays: int,
     *     isReady: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hue' => $this->hue,
            'loggedDays' => $this->loggedDays,
            'requiredDays' => $this->requiredDays,
            'remainingDays' => $this->remainingDays(),
            'isReady' => $this->isReady(),
        ];
    }
}
