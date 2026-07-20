<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FoodEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line item within a Meal. It carries the raw `text` the user typed
 * while it awaits classification, and/or a `food_item_id` once the classifier
 * has resolved it against the curated catalog (D9). A database check
 * constraint guarantees at least one of the two is present.
 *
 * `food_item_id` is deliberately unconstrained by a foreign key: the
 * `food_items` catalog it points at arrives with SUI-14.
 *
 * @property int $id
 * @property int $meal_id
 * @property int|null $food_item_id
 * @property string|null $text
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Meal $meal
 * @property-read CarbonImmutable|null $eaten_at
 */
#[Fillable(['meal_id', 'food_item_id', 'text'])]
class FoodEntry extends Model
{
    /** @use HasFactory<FoodEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Meal, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * Whether the classifier has resolved this entry to a catalog food item.
     */
    public function isClassified(): bool
    {
        return $this->food_item_id !== null;
    }

    /**
     * Whether this entry is still free text awaiting classification.
     */
    public function isPendingClassification(): bool
    {
        return ! $this->isClassified();
    }

    /**
     * The instant this entry was eaten — inherited from its parent meal, which
     * is where the timestamp lives. Guarded because strict mode forbids
     * lazy-loading `meal`; backfill with `setRelation('meal', $meal)` wherever
     * a meal's entries are iterated or rendered.
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function eatenAt(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->relationLoaded('meal')
            ? $this->meal->eaten_at
            : null);
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
