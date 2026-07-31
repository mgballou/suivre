<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use App\Enums\RampStep;
use App\Models\Condition;
use App\Models\ConditionLog;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One tracked condition as it appears on a day: its identity, the 0–10 rating
 * saved for that day if there is one, and the ramp step that rating sits at.
 *
 * `level` is derived server-side because the ramp's bucketing lives in
 * RampStep — the day screen and the calendar must not disagree about which
 * rating reads at which step.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DayCondition implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $hue,
        public ?int $intensity,
        public int $level,
    ) {}

    /**
     * @return array{id: int, name: string, hue: string, intensity: int|null, level: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hue' => $this->hue,
            'intensity' => $this->intensity,
            'level' => $this->level,
        ];
    }

    public static function fromCondition(Condition $condition, ?ConditionLog $log): self
    {
        return new self(
            id: $condition->id,
            name: $condition->name,
            hue: $condition->color->value,
            intensity: $log?->intensity,
            level: RampStep::fromRating($log?->intensity)->value,
        );
    }
}
