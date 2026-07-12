<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-defined condition tracked over time via daily ratings and acute flare
 * events. Owned by exactly one user; ownership is enforced through `user_id`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color
 * @property string $icon
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, ConditionLog> $conditionLogs
 * @property-read Collection<int, FlareEvent> $flareEvents
 */
#[Fillable(['user_id', 'name', 'color', 'icon', 'is_active'])]
class Condition extends Model
{
    /** @use HasFactory<ConditionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ConditionLog, $this>
     */
    public function conditionLogs(): HasMany
    {
        return $this->hasMany(ConditionLog::class);
    }

    /**
     * @return HasMany<FlareEvent, $this>
     */
    public function flareEvents(): HasMany
    {
        return $this->hasMany(FlareEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
