<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use Carbon\CarbonImmutable;
use Database\Factories\DailyCheckinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check-in per user per local calendar day. The `(user_id, date)` pair is
 * unique at the database level; `date` is the user's local day, resolved by
 * ResolveUserDay, not a UTC instant.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $date
 * @property SleepQuality|null $sleep
 * @property MoodLevel|null $mood
 * @property StressLevel|null $stress
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'date', 'sleep', 'mood', 'stress', 'note'])]
class DailyCheckin extends Model
{
    /** @use HasFactory<DailyCheckinFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'sleep' => SleepQuality::class,
            'mood' => MoodLevel::class,
            'stress' => StressLevel::class,
        ];
    }
}
