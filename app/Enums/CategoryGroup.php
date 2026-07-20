<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The governance bucket a trigger category belongs to (D9/D10).
 *
 * The split matters to the classifier: Allergen and Ingredient categories can be
 * auto-derived from a structured food dataset's allergen/ingredient fields, while
 * Research categories have no dataset equivalent and are curated by hand and grown
 * through the review queue.
 */
enum CategoryGroup: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Allergen = 'allergen';
    case Ingredient = 'ingredient';
    case Research = 'research';

    public function getLabel(): string
    {
        return match ($this) {
            self::Allergen => 'Allergen',
            self::Ingredient => 'Ingredient / additive',
            self::Research => 'Research',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Allergen => 'danger',
            self::Ingredient => 'warning',
            self::Research => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Allergen => 'heroicon-o-exclamation-triangle',
            self::Ingredient => 'heroicon-o-beaker',
            self::Research => 'heroicon-o-academic-cap',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Allergen => 'Major allergens carried as structured data by the food catalog.',
            self::Ingredient => 'Ingredients and additives detected by scanning ingredient statements.',
            self::Research => 'Research-based groupings the food catalog cannot supply; curated by hand.',
        };
    }

    public function isAllergen(): bool
    {
        return $this === self::Allergen;
    }

    public function isIngredient(): bool
    {
        return $this === self::Ingredient;
    }

    public function isResearch(): bool
    {
        return $this === self::Research;
    }

    public function isAutoDerivable(): bool
    {
        return in_array($this, self::autoDerivable(), strict: true);
    }

    public function isCuratedOnly(): bool
    {
        return in_array($this, self::curatedOnly(), strict: true);
    }

    /**
     * Groups the catalog import can populate on its own from structured
     * allergen and ingredient data (D10).
     *
     * @return array<int, self>
     */
    public static function autoDerivable(): array
    {
        return [
            self::Allergen,
            self::Ingredient,
        ];
    }

    /**
     * Groups with no dataset equivalent — populated only by operator curation
     * and the classification review queue (D10).
     *
     * @return array<int, self>
     */
    public static function curatedOnly(): array
    {
        return [
            self::Research,
        ];
    }
}
