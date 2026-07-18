<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ConditionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One daily 0–10 intensity rating per user per condition per local calendar
 * day. The `(user_id, condition_id, date)` triple is unique at the database
 * level; `date` is the user's local day, not a UTC instant.
 *
 * @property int $id
 * @property int $user_id
 * @property int $condition_id
 * @property CarbonImmutable $date
 * @property int $intensity
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Condition $condition
 */
#[Fillable(['user_id', 'condition_id', 'date', 'intensity'])]
class ConditionLog extends Model
{
    /** @use HasFactory<ConditionLogFactory> */
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
            'date' => 'immutable_date',
            'intensity' => 'integer',
        ];
    }
}
