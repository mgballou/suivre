<?php

declare(strict_types=1);

namespace App\Services\Meals\Actions;

use App\Enums\MealType;
use App\Enums\ReviewStatus;
use App\Models\FoodEntry;
use App\Models\Meal;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Food\Actions\ClassifyFoodEntry;
use App\Services\Meals\Data\MealEntryDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LogMeal
{
    /**
     * Save a meal and its line items for a user's local day.
     *
     * The user has already confirmed each line, so a resolved draft is taken at
     * its word rather than reclassified. An unresolved one is stored as free
     * text — never rejected, because a miss must not block logging (D9) — and
     * queued for review so the catalog can learn it.
     *
     * @param  array<int, MealEntryDraft>  $entries
     */
    public function __invoke(
        User $user,
        CarbonImmutable $date,
        MealType $mealType,
        array $entries,
    ): Meal {
        return DB::transaction(function () use ($user, $date, $mealType, $entries): Meal {
            $meal = Meal::query()->create([
                'user_id' => $user->id,
                'eaten_at' => app(ResolveMealMoment::class)($user, $date, $mealType, CarbonImmutable::now()),
                'meal_type' => $mealType,
            ]);

            foreach ($entries as $draft) {
                $this->record($meal, $draft);
            }

            return $meal;
        });
    }

    private function record(Meal $meal, MealEntryDraft $draft): void
    {
        $entry = FoodEntry::query()->create([
            'meal_id' => $meal->id,
            'food_item_id' => $draft->foodItemId,
            'text' => $draft->text,
        ]);

        if ($draft->isResolved()) {
            return;
        }

        $this->queueForReview($entry, $draft->text);
    }

    /**
     * Park an unmatched line for an operator.
     *
     * The classifier is re-run for its *score* alone: the user rejected or
     * never received a suggestion, and an operator still needs to tell a near
     * miss worth an alias from a word the catalog has never heard of.
     *
     * `updateOrCreate` keyed on the entry honours the table's one-item-per-record
     * uniqueness, so re-saving cannot queue the same miss twice.
     */
    private function queueForReview(FoodEntry $entry, string $text): void
    {
        $score = app(ClassifyFoodEntry::class)($text)->score;

        ReviewItem::query()->updateOrCreate(
            [
                'reviewable_type' => $entry->getMorphClass(),
                'reviewable_id' => $entry->getKey(),
            ],
            [
                'text' => $text,
                'score' => $score > 0.0 ? $score : null,
                'status' => ReviewStatus::Pending,
            ],
        );
    }
}
