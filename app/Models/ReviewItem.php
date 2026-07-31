<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A classification miss parked for an operator (D9).
 *
 * The deterministic classifier either matches the catalog or it does not; when
 * it does not, the user is never interrupted — the entry saves as free text and
 * the question lands here instead. Resolving one means curating the catalog, so
 * the same text matches next time.
 *
 * SUI-17 builds the Filament queue over this table.
 *
 * @property int $id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property string $text
 * @property float|null $score
 * @property ReviewStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model $reviewable
 */
#[Fillable(['reviewable_type', 'reviewable_id', 'text', 'score', 'status'])]
class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this item still awaits an operator decision. Reads the predicate
     * off the enum rather than comparing the status here.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewable_id' => 'integer',
            'score' => 'float',
            'status' => ReviewStatus::class,
        ];
    }
}
