<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FoodItemAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A synonym a catalog food is also known by — "aubergine" for "eggplant",
 * "soda" for "soft drink". The synonym-resolution step of the deterministic
 * classifier (D9) runs against these.
 *
 * They live in their own table rather than a JSON column on FoodItem so each
 * alias carries its own normalized form and its own GIN trigram index; a JSON
 * array cannot be fuzzy-matched by the same indexed query as a canonical name.
 *
 * @property int $id
 * @property int $food_item_id
 * @property string $alias
 * @property string $normalized_alias
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read FoodItem $foodItem
 */
#[Fillable(['food_item_id', 'alias'])]
class FoodItemAlias extends Model
{
    /** @use HasFactory<FoodItemAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<FoodItem, $this>
     */
    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }

    /**
     * Derives `normalized_alias` from `alias` through the catalog's single
     * normalization rule, for the same reason FoodItem derives its own.
     *
     * @return Attribute<never, string>
     */
    protected function alias(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'alias' => $value,
            'normalized_alias' => FoodItem::normalizeName($value),
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'food_item_id' => 'integer',
        ];
    }
}
