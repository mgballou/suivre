<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SleepQuality: int implements HasColor, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Poor = 1;
    case Good = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Poor => 'Poor',
            self::Good => 'Good',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Poor => 'danger',
            self::Good => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Poor => 'heroicon-o-cloud',
            self::Good => 'heroicon-o-moon',
        };
    }

    public function isPoor(): bool
    {
        return $this === self::Poor;
    }

    public function isGood(): bool
    {
        return $this === self::Good;
    }
}
