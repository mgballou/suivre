<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FlareIntensity;
use Carbon\CarbonImmutable;
use Database\Factories\FlareEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An acute flare-up of a condition at a point in time — the event half of the
 * hybrid conditions model. Always tied to a condition; ownership is enforced
 * through `user_id`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $condition_id
 * @property CarbonImmutable $occurred_at
 * @property FlareIntensity $intensity
 * @property int|null $duration_minutes
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Condition $condition
 */
#[Fillable(['user_id', 'condition_id', 'occurred_at', 'intensity', 'duration_minutes', 'note'])]
class FlareEvent extends Model
{
    /** @use HasFactory<FlareEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'intensity' => FlareIntensity::class,
            'duration_minutes' => 'integer',
        ];
    }
}
