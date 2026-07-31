<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Models\Condition;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The ranking for one condition, ready to render.
 *
 * Only conditions past the volume gate reach this DTO at all — a condition still
 * short of the threshold is described by `ConditionReadiness` instead, and the
 * two never appear for the same condition. That split is deliberate: an
 * insufficient-data outcome is a different statement from an empty ranking, and
 * flattening them into one "nothing found" is exactly the confusion
 * `CorrelationReport::suspects()` throws to prevent.
 *
 * An empty `suspects` list is therefore meaningful and gets its own copy: enough
 * has been logged, and nothing separated itself from chance.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ConditionInsight implements Arrayable
{
    /**
     * @param  array<int, SuspectHint>  $suspects
     */
    public function __construct(
        public int $conditionId,
        public string $conditionName,
        public string $hue,
        public array $suspects,
        public int $loggedDays,
        public int $windowDays,
    ) {}

    /**
     * @return array{
     *     conditionId: int,
     *     conditionName: string,
     *     hue: string,
     *     suspects: array<int, array<string, mixed>>,
     *     loggedDays: int,
     *     windowDays: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'conditionId' => $this->conditionId,
            'conditionName' => $this->conditionName,
            'hue' => $this->hue,
            'suspects' => array_map(
                static fn (SuspectHint $hint): array => $hint->toArray(),
                $this->suspects,
            ),
            'loggedDays' => $this->loggedDays,
            'windowDays' => $this->windowDays,
        ];
    }

    /**
     * @param  array<int, SuspectHint>  $suspects
     */
    public static function fromReport(Condition $condition, CorrelationReport $report, array $suspects): self
    {
        return new self(
            conditionId: $condition->id,
            conditionName: $condition->name,
            hue: $condition->color->value,
            suspects: $suspects,
            loggedDays: $report->loggedDays,
            windowDays: $report->windowDays,
        );
    }
}
