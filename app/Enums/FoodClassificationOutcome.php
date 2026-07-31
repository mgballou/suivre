<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What `ClassifyFoodEntry` concluded about a piece of free text (D9).
 *
 * A type-safe outcome rather than a nullable match, so a caller cannot treat
 * an unmatched result as a match by accident — `$result->foodItem` is only
 * meaningful once `$result->outcome->isMatched()` has been checked.
 * `LowConfidence` is the seam SUI-17's review queue consumes; this ticket only
 * makes the outcome available, it does not build the queue.
 */
enum FoodClassificationOutcome: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Matched = 'matched';
    case LowConfidence = 'low_confidence';

    public function getLabel(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::LowConfidence => 'Low confidence',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matched => 'success',
            self::LowConfidence => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Matched => 'heroicon-o-check-circle',
            self::LowConfidence => 'heroicon-o-question-mark-circle',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Matched => 'The catalog match cleared the similarity threshold.',
            self::LowConfidence => 'No catalog entry cleared the similarity threshold; queued for review (SUI-17).',
        };
    }

    public function isMatched(): bool
    {
        return $this === self::Matched;
    }

    public function isLowConfidence(): bool
    {
        return $this === self::LowConfidence;
    }

    /**
     * Outcomes that resolve to a usable `FoodItem`.
     *
     * @return array<int, self>
     */
    public static function matched(): array
    {
        return [
            self::Matched,
        ];
    }

    /**
     * Outcomes that carry no usable match and need human review (SUI-17).
     *
     * @return array<int, self>
     */
    public static function lowConfidence(): array
    {
        return [
            self::LowConfidence,
        ];
    }
}
