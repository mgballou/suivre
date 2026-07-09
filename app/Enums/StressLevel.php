<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StressLevel: int implements HasColor, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Low = 1;
    case Moderate = 2;
    case High = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Moderate => 'Moderate',
            self::High => 'High',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Moderate => 'warning',
            self::High => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-check-circle',
            self::Moderate => 'heroicon-o-exclamation-circle',
            self::High => 'heroicon-o-exclamation-triangle',
        };
    }

    public function isLow(): bool
    {
        return $this === self::Low;
    }

    public function isModerate(): bool
    {
        return $this === self::Moderate;
    }

    public function isHigh(): bool
    {
        return $this === self::High;
    }

    public function isElevated(): bool
    {
        return in_array($this, self::elevated(), strict: true);
    }

    /**
     * Stress levels that count as elevated — the grouping the correlation
     * engine and check-in UI treat as a "stressful day".
     *
     * @return array<int, self>
     */
    public static function elevated(): array
    {
        return [
            self::Moderate,
            self::High,
        ];
    }
}
