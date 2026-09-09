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
 * An empty `suspects` list is not one statement but two, and `measuredTags`
 * separates them (D30). With tags measured it means enough was logged, every
 * food that could be compared was, and none came out above baseline — a result.
 * At zero it means nothing in the log could be compared at all, which is a gap
 * the surface has to name as one rather than dress as a finding. `thinTags`
 * carries how many foods were seen and dropped short of the day floors, so the
 * gap can say what would close it.
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
        public int $measuredTags,
        public int $thinTags,
    ) {}

    /**
     * @return array{
     *     conditionId: int,
     *     conditionName: string,
     *     hue: string,
     *     suspects: array<int, array<string, mixed>>,
     *     loggedDays: int,
     *     windowDays: int,
     *     measuredTags: int,
     *     thinTags: int,
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
            'measuredTags' => $this->measuredTags,
            'thinTags' => $this->thinTags,
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
            measuredTags: $report->measuredTags,
            thinTags: $report->thinTags,
        );
    }
}
