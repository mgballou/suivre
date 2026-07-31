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
            suspects: [],
        );
    }

    /**
     * A ranking, ordered by lift descending. It may legitimately be empty when
     * no tag has enough exposed and baseline days to measure.
     *
     * @param  array<int, CorrelationSuspect>  $suspects
     */
    public static function ranked(array $suspects, int $loggedDays, int $requiredDays, int $windowDays): self
    {
        return new self(
            status: CorrelationStatus::Ready,
            loggedDays: $loggedDays,
            requiredDays: $requiredDays,
            windowDays: $windowDays,
            suspects: $suspects,
        );
    }
}
