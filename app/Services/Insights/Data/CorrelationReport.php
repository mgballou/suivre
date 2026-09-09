<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Enums\CorrelationStatus;
use App\Exceptions\Insights\InsufficientCorrelationDataException;
use Illuminate\Contracts\Support\Arrayable;

/**
 * What `ComputeCorrelations` hands back for one user × condition.
 *
 * The report has two shapes and only two, minted through the named
 * constructors: an insufficient-data outcome that carries no ranking, and a
 * ready outcome that does. `suspects()` throws on the former rather than
 * returning an empty list, so a caller cannot accidentally render "nothing
 * found" over "not enough logged yet" — SUI-36 findings 1 and 6 make those two
 * statements very different claims.
 *
 * `reportNoiseBand` is the bar every row on the ready report is gated on: the
 * lift the strongest of these tags reaches when they are all rotated away from
 * the ratings together (D29). It belongs to the report rather than to any row
 * because it is a property of how many tags were tested at once.
 *
 * `measuredTags` and `thinTags` exist so an empty ranking can say which empty it
 * means (D30). Nine tags measured and none above baseline is a result; nine tags
 * seen and none of them on enough days to compare is the absence of one, and
 * before these counts both arrived as the same empty array.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class CorrelationReport implements Arrayable
{
    /**
     * @param  array<int, CorrelationSuspect>  $suspects
     */
    private function __construct(
        public CorrelationStatus $status,
        public int $loggedDays,
        public int $requiredDays,
        public int $windowDays,
        public ?float $reportNoiseBand,
        public int $measuredTags,
        public int $thinTags,
        private array $suspects,
    ) {}

    /**
     * @return array<int, CorrelationSuspect>
     */
    public function suspects(): array
    {
        throw_if(
            condition: $this->status->isInsufficient(),
            exception: InsufficientCorrelationDataException::make($this->loggedDays, $this->requiredDays),
        );

        return $this->suspects;
    }

    /**
     * @return array{
     *     status: string,
     *     loggedDays: int,
     *     requiredDays: int,
     *     windowDays: int,
     *     reportNoiseBand: float|null,
     *     measuredTags: int,
     *     thinTags: int,
     *     suspects: array<int, array<string, mixed>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'loggedDays' => $this->loggedDays,
            'requiredDays' => $this->requiredDays,
            'windowDays' => $this->windowDays,
            'reportNoiseBand' => $this->reportNoiseBand,
            'measuredTags' => $this->measuredTags,
            'thinTags' => $this->thinTags,
            'suspects' => array_map(
                static fn (CorrelationSuspect $suspect): array => $suspect->toArray(),
                $this->suspects,
            ),
        ];
    }

    /**
     * The user has not logged enough days for any ranking to be honest.
     */
    public static function insufficientData(int $loggedDays, int $requiredDays, int $windowDays): self
    {
        return new self(
            status: CorrelationStatus::InsufficientData,
            loggedDays: $loggedDays,
            requiredDays: $requiredDays,
            windowDays: $windowDays,
            reportNoiseBand: null,
            measuredTags: 0,
            thinTags: 0,
            suspects: [],
        );
    }

    /**
     * A ranking, ordered by lift descending, holding only tags whose lift is
     * above baseline.
     *
     * It may legitimately be empty, and the two counts say why: `measuredTags`
     * is how many tags cleared the exposed and baseline day floors, `thinTags`
     * how many were seen in the log and dropped short of them. Empty with
     * `measuredTags` above zero is a measurement that found nothing; empty with
     * `measuredTags` at zero is no measurement at all (D30).
     *
     * @param  array<int, CorrelationSuspect>  $suspects
     */
    public static function ranked(
        array $suspects,
        int $loggedDays,
        int $requiredDays,
        int $windowDays,
        ?float $reportNoiseBand,
        int $measuredTags,
        int $thinTags,
    ): self {
        return new self(
            status: CorrelationStatus::Ready,
            loggedDays: $loggedDays,
            requiredDays: $requiredDays,
            windowDays: $windowDays,
            reportNoiseBand: $reportNoiseBand,
            measuredTags: $measuredTags,
            thinTags: $thinTags,
            suspects: $suspects,
        );
    }
}
