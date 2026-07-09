<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MoodLevel: int implements HasColor, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Low = 1;
    case Neutral = 2;
    case Good = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Neutral => 'Neutral',
            self::Good => 'Good',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'danger',
            self::Neutral => 'warning',
            self::Good => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-face-frown',
            self::Neutral => 'heroicon-o-minus-circle',
            self::Good => 'heroicon-o-face-smile',
        };
    }

    public function isLow(): bool
    {
        return $this === self::Low;
    }

    public function isNeutral(): bool
    {
        return $this === self::Neutral;
    }

    public function isGood(): bool
    {
        return $this === self::Good;
    }
}
