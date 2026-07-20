<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MealType;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;
use Database\Factories\MealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An eating occasion — the container that groups FoodEntry line items. Owned by
 * exactly one user; ownership is enforced through `user_id`. Per decision-log
 * D5 a meal attaches to a day, but it stores an instant (`eaten_at`) and
 * derives that day through ResolveUserDay so the user's timezone stays
 * authoritative.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $eaten_at
 * @property MealType $meal_type
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, FoodEntry> $entries
 * @property-read CarbonImmutable|null $date
 */
#[Fillable(['user_id', 'eaten_at', 'meal_type'])]
class Meal extends Model
{
    /** @use HasFactory<MealFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<FoodEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(FoodEntry::class);
    }

    /**
     * The user's local calendar day this meal belongs to (D5). Resolving it
     * needs the owning user's timezone, so the accessor yields null unless
     * `user` is loaded — strict mode forbids lazy-loading it here.
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function date(): Attribute
    {
        return Attribute::get(fn (): ?CarbonImmutable => $this->relationLoaded('user')
            ? app(ResolveUserDay::class)($this->user, $this->eaten_at)
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
            'eaten_at' => 'immutable_datetime',
            'meal_type' => MealType::class,
        ];
    }
}
