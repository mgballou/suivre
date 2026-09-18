<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

/**
 * The two bands one rotation pass produces: one per ranked row, and one for the
 * report as a whole.
 *
 * `report` is the load-bearing one. A row's own band answers "would this tag
 * alone reach this lift by coincidence", and answering that eight times over
 * makes a coincidence somewhere on the page the ordinary case rather than the
 * rare one. `report` answers the question the page actually asks — "would the
 * *best* of these tags reach this lift by coincidence" — so it is the bar every
 * row is gated on. The per-row bands are kept because they are what a reader
 * comparing one row against itself would want, and because they cost nothing:
 * both come out of the same rotations.
 */
readonly class NoiseBands
{
    /**
     * @param  array<int, float|null>  $perRow  keyed as the masks were, null where no band could be drawn
     */
    public function __construct(
        public array $perRow,
        public ?float $report,
    ) {}

    public function forRow(int $row): ?float
    {
        return $this->perRow[$row] ?? null;
    }

    /**
     * Whether a lift beats the bar the whole report is gated on.
     *
     * An unestimable band reads as not clearing: the caller reports the row as
     * inside the range chance produces rather than clearing on a guess.
     */
    public function clears(float $lift): bool
    {
        return $this->report !== null && $lift > $this->report;
    }

    public static function none(): self
    {
        return new self(perRow: [], report: null);
    }
}
