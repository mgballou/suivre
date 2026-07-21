<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FoodItemType;
use Carbon\CarbonImmutable;
use Database\Factories\FoodItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An entry in the global food catalog — the curated knowledge base free-text
 * food entries are resolved against (D9). Global like Category: operator-owned
 * reference data with no owning user, bootstrapped from Open Food Facts and
 * grown by the review queue (D10).
 *
 * Its categories are the whole point: a resolved FoodEntry inherits them, and
 * they are what the correlation engine ranks.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property FoodItemType $type
 * @property string|null $source
 * @property string|null $source_ref
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, FoodItemAlias> $aliases
 * @property-read Collection<int, FoodItem> $components
 * @property-read Collection<int, FoodItem> $dishes
 */
#[Fillable(['name', 'type', 'source', 'source_ref'])]
class FoodItem extends Model
{
    /** @use HasFactory<FoodItemFactory> */
    use HasFactory;

    /**
     * The curated trigger categories this food carries (D9).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /**
     * Synonyms this food is also known by, each independently trigram-indexed.
     *
     * @return HasMany<FoodItemAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(FoodItemAlias::class);
    }

    /**
     * The catalog items a composite dish is made of (D9).
     *
     * @return BelongsToMany<FoodItem, $this>
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'food_item_compositions',
            'food_item_id',
            'component_id',
        )->withTimestamps();
    }

    /**
     * The composite dishes this food appears in — the inverse of `components`.
     *
     * @return BelongsToMany<FoodItem, $this>
     */
    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'food_item_compositions',
            'component_id',
            'food_item_id',
        )->withTimestamps();
    }

    /**
     * Whether this row came from a dataset import rather than curation (D10).
     */
    public function isImported(): bool
    {
        return $this->source !== null;
    }

    /**
     * Whether an operator authored this row by hand.
     */
    public function isCurated(): bool
    {
        return ! $this->isImported();
    }

    /**
     * Keeps `normalized_name` derived rather than assignable, so no call site
     * can persist a name whose indexed form disagrees with it.
     *
     * @return Attribute<never, string>
     */
    protected function name(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'name' => $value,
            'normalized_name' => self::normalizeName($value),
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
            'type' => FoodItemType::class,
        ];
    }

    /**
     * Reduce a display name to the form the trigram indexes store and the
     * classifier matches against: transliterated to ASCII, lowercased, every
     * run of non-alphanumerics collapsed to a single space, trimmed.
     *
     * Deliberately lossy and deliberately shared — SUI-16 normalizes the user's
     * raw text through this same method, so both sides of a similarity
     * comparison are produced identically.
     */
    public static function normalizeName(string $value): string
    {
        $ascii = Str::lower(Str::ascii($value));

        return trim(Str::squish((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii)));
    }
}
