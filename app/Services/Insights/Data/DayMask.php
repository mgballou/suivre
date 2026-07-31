<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

/**
 * A boolean flag per day over one contiguous span, indexed from 0.
 *
 * The same shape carries both a tag's occurrence days and the exposed days its
 * lag window covers, because every operation the engine performs — union,
 * complement, overlap — is the same operation on both.
 */
readonly class DayMask
{
    /**
     * @param  array<int, bool>  $days
     */
    public function __construct(public array $days) {}

    public function length(): int
    {
        return count($this->days);
    }

    public function count(): int
    {
        return count(array_filter($this->days));
    }

    /**
     * The day indexes that are set, ascending.
     *
     * @return array<int, int>
     */
    public function indexes(): array
    {
        return array_keys(array_filter($this->days));
    }

    public function complement(): self
    {
        return new self(array_map(static fn (bool $day): bool => ! $day, $this->days));
    }

    public function union(self $other): self
    {
        return new self(array_map(
            static fn (bool $day, bool $otherDay): bool => $day || $otherDay,
            $this->days,
            $other->days,
        ));
    }

    /**
     * The days set here and not set in `$other`.
     */
    public function without(self $other): self
    {
        return new self(array_map(
            static fn (bool $day, bool $otherDay): bool => $day && ! $otherDay,
            $this->days,
            $other->days,
        ));
    }

    /**
     * Jaccard overlap — the share of days either mask covers that both cover.
     * Two masks that never fire together score 0; identical masks score 1.
     */
    public function overlap(self $other): float
    {
        $intersection = 0;
        $union = 0;

        foreach ($this->days as $index => $day) {
            $otherDay = $other->days[$index] ?? false;

            if ($day && $otherDay) {
                $intersection++;
            }

            if ($day || $otherDay) {
                $union++;
            }
        }

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * The mask rotated forward by `$offset` days, wrapping at the end.
     *
     * Rotation is how the noise band is drawn: it preserves how often the tag
     * fires and how it clumps, while destroying any real alignment with the
     * intensity series.
     */
    public function rotate(int $offset): self
    {
        $length = $this->length();

        if ($length === 0) {
            return $this;
        }

        $shift = (($offset % $length) + $length) % $length;

        return new self(array_merge(
            array_slice($this->days, $length - $shift),
            array_slice($this->days, 0, $length - $shift),
        ));
    }

    /**
     * A mask with the given day indexes set.
     *
     * @param  array<int, int>  $indexes
     */
    public static function of(int $length, array $indexes): self
    {
        $days = $length > 0 ? array_fill(0, $length, false) : [];

        foreach ($indexes as $index) {
            if ($index >= 0 && $index < $length) {
                $days[$index] = true;
            }
        }

        return new self($days);
    }
}
