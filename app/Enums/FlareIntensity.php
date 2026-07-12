<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FlareIntensity: int implements HasColor, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Mild = 1;
    case Moderate = 2;
    case Severe = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Mild => 'Mild',
            self::Moderate => 'Moderate',
            self::Severe => 'Severe',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Mild => 'success',
            self::Moderate => 'warning',
            self::Severe => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Mild => 'heroicon-o-check-circle',
            self::Moderate => 'heroicon-o-exclamation-circle',
            self::Severe => 'heroicon-o-exclamation-triangle',
        };
    }

    public function isMild(): bool
    {
        return $this === self::Mild;
    }

    public function isModerate(): bool
    {
        return $this === self::Moderate;
    }

    public function isSevere(): bool
    {
        return $this === self::Severe;
    }

    public function isSignificant(): bool
    {
        return in_array($this, self::significant(), strict: true);
    }

    /**
     * Flare intensities that count as clinically significant — the grouping the
     * correlation engine and insights treat as a "notable flare".
     *
     * @return array<int, self>
     */
    public static function significant(): array
    {
        return [
            self::Moderate,
            self::Severe,
        ];
    }
}
