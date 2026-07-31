<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\Meal;
use App\Models\User;
use App\Services\Insights\Data\ExposureHistory;
use App\Services\Insights\Data\SuspectTag;
use App\Services\Journal\Actions\ResolveDayBounds;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;

/**
 * Read-side access to the two series the correlation engine correlates: a
 * condition's daily intensity, and the trigger categories a user was exposed to
 * each day.
 *
 * Both are pulled in bulk. The engine walks a user's whole history several
 * times over, so anything per-day here would be a per-day query.
 */
class CorrelationDataRepository
{
    /**
     * The user's rating of one condition per local calendar day, keyed `Y-m-d`.
     *
     * A `(user, condition, date)` triple is unique at the database level, so
     * there is nothing to aggregate — unlike the journal's day view, which has
     * to reduce several conditions to the worst of them.
     *
     * @return array<string, int>
     */
    public function dailyIntensity(User $user, Condition $condition): array
    {
        return ConditionLog::query()
            ->where('user_id', $user->getKey())
            ->where('condition_id', $condition->getKey())
            ->orderBy('date')
            ->get()
            ->mapWithKeys(static fn (ConditionLog $log): array => [
                $log->date->toDateString() => $log->intensity,
            ])
            ->all();
    }

    /**
     * The trigger categories the user's classified food entries resolved to,
     * grouped by the local day their meal was eaten on.
     *
     * Days come from `ResolveUserDay` rather than the stored instant, so a meal
     * eaten at 00:30 belongs to the day the user was living, not the UTC one.
     * The span's edges are widened by `ResolveDayBounds` for the same reason.
     * An entry still awaiting classification has no food item and therefore no
     * categories; it contributes nothing rather than an empty tag.
     */
    public function exposureHistory(User $user, CarbonImmutable $start, CarbonImmutable $end): ExposureHistory
    {
        $bounds = app(ResolveDayBounds::class);
        $resolveDay = app(ResolveUserDay::class);

        $meals = Meal::query()
            ->where('user_id', $user->getKey())
            ->where('eaten_at', '>=', $bounds($user, $start)->startsAt)
            ->where('eaten_at', '<', $bounds($user, $end)->endsAt)
            ->with('entries.foodItem.categories')
            ->orderBy('eaten_at')
            ->get();

        $categoryIdsByDate = [];
        $tags = [];

        foreach ($meals as $meal) {
            $date = $resolveDay($user, $meal->eaten_at)->toDateString();

            foreach ($meal->entries as $entry) {
                $foodItem = $entry->foodItem;

                if ($foodItem === null) {
                    continue;
                }

                foreach ($foodItem->categories as $category) {
                    $categoryIdsByDate[$date][$category->id] = $category->id;
                    $tags[$category->id] ??= SuspectTag::fromCategory($category);
                }
            }
        }

        foreach ($categoryIdsByDate as $date => $categoryIds) {
            ksort($categoryIds);
            $categoryIdsByDate[$date] = array_values($categoryIds);
        }

        ksort($tags);

        return new ExposureHistory(
            categoryIdsByDate: $categoryIdsByDate,
            tags: $tags,
        );
    }
}
