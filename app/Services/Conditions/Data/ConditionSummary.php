<?php

declare(strict_types=1);

namespace App\Services\Conditions\Data;

use App\Models\Condition;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One row of the conditions screen.
 *
 * The two counts are the point of the row rather than decoration: a condition
 * is never deleted, only deactivated, and showing what it has accumulated is
 * how "stopping" reads as archiving rather than as losing something.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ConditionSummary implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $hue,
        public bool $isActive,
        public int $ratings,
        public int $flares,
    ) {}

    /**
     * @return array{id: int, name: string, hue: string, isActive: bool, ratings: int, flares: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hue' => $this->hue,
            'isActive' => $this->isActive,
            'ratings' => $this->ratings,
            'flares' => $this->flares,
        ];
    }

    /**
     * Requires the record to have been loaded with both counts; strict mode
     * throws rather than silently reporting zero if a call site forgets.
     */
    public static function fromCondition(Condition $condition): self
    {
        return new self(
            id: $condition->id,
            name: $condition->name,
            hue: $condition->color->value,
            isActive: $condition->is_active,
            ratings: (int) $condition->condition_logs_count,
            flares: (int) $condition->flare_events_count,
        );
    }
}
