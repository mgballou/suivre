<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The slot of the day a meal was eaten in. Unlike the ordinal scales
 * (FlareIntensity, MoodLevel, …) this is a nominal category, so it is
 * string-backed — the stored value stays legible in the database and in
 * exported correlation data.
 */
enum MealType: string implements HasColor, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';

    public function getLabel(): string
    {
        return match ($this) {
            self::Breakfast => 'Breakfast',
            self::Lunch => 'Lunch',
            self::Dinner => 'Dinner',
            self::Snack => 'Snack',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Breakfast => 'warning',
            self::Lunch => 'success',
            self::Dinner => 'info',
            self::Snack => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Breakfast => 'heroicon-o-sun',
            self::Lunch => 'heroicon-o-clock',
            self::Dinner => 'heroicon-o-moon',
            self::Snack => 'heroicon-o-cake',
        };
    }

    public function isBreakfast(): bool
    {
        return $this === self::Breakfast;
    }

    public function isLunch(): bool
    {
        return $this === self::Lunch;
    }

    public function isDinner(): bool
    {
        return $this === self::Dinner;
    }

    public function isSnack(): bool
    {
        return $this === self::Snack;
    }

    public function isMainMeal(): bool
    {
        return in_array($this, self::mainMeals(), strict: true);
    }

    /**
     * The three anchored sittings of the day — the grouping the correlation
     * engine treats as a substantial eating occasion, as opposed to a snack.
     *
     * @return array<int, self>
     */
    public static function mainMeals(): array
    {
        return [
            self::Breakfast,
            self::Lunch,
            self::Dinner,
        ];
    }
}
