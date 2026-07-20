<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What a catalog entry represents, and therefore how the classifier is allowed
 * to derive its categories (D9/D10).
 *
 * An Item is an atomic food or packaged product — the shape Open Food Facts
 * imports arrive in, whose categories come from its own allergen/ingredient
 * data. A Dish is a composite the dataset cannot describe (curry, carbonara);
 * its categories are curated and it inherits further signal from the components
 * linked to it, so it is the only kind that carries a composition.
 */
enum FoodItemKind: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    use HasOrderedCases;

    case Item = 'item';
    case Dish = 'dish';

    public function getLabel(): string
    {
        return match ($this) {
            self::Item => 'Item',
            self::Dish => 'Composite dish',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Item => 'gray',
            self::Dish => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Item => 'heroicon-o-cube',
            self::Dish => 'heroicon-o-squares-2x2',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Item => 'An atomic food or packaged product, tagged from its own data.',
            self::Dish => 'A curated composite dish that draws further signal from its components.',
        };
    }

    public function isItem(): bool
    {
        return $this === self::Item;
    }

    public function isDish(): bool
    {
        return $this === self::Dish;
    }

    /**
     * Whether this kind may carry a curated component list (D9).
     */
    public function isComposite(): bool
    {
        return in_array($this, self::composite(), strict: true);
    }

    /**
     * Whether a dataset import may create this kind on its own (D10).
     */
    public function isImportable(): bool
    {
        return in_array($this, self::importable(), strict: true);
    }

    /**
     * Kinds whose categories are assembled from a curated composition rather
     * than from the imported dataset alone (D9).
     *
     * @return array<int, self>
     */
    public static function composite(): array
    {
        return [
            self::Dish,
        ];
    }

    /**
     * Kinds a dataset import can create directly from structured product data
     * (D10). The import never invents a composite dish.
     *
     * @return array<int, self>
     */
    public static function importable(): array
    {
        return [
            self::Item,
        ];
    }
}
