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
 * Read-side access to the three series the correlation engine reads: a
 * condition's daily intensity, the trigger categories a user was exposed to each
 * day, and the days on which the user logged a meal at all.
 *
 * The third exists because the first two cannot distinguish "no meal" from "a
 * meal with none of these foods in it" — the exposure map is keyed by the days
 * that produced a category, so a day the user never opened the app is
 * indistinguishable from a day of plain rice (D31).
 *
 * All three are pulled in bulk. The engine walks a user's whole history several
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
     * The local calendar days on which the user logged at least one meal.
     *
     * Coverage is "did the user log", not "did the classifier finish": a meal
     * whose entries are still awaiting a food item covers its day, because the
     * user did record eating and the gap is the pipeline's, not theirs. A meal
     * with no trigger categories in it covers its day too — that is a genuine
     * baseline day and the whole point of keeping it.
     *
     * Days come from `ResolveUserDay` and the span edges from `ResolveDayBounds`,
     * exactly as `exposureHistory` does, so the two never disagree about which
     * day a late-night meal belongs to. Passing no span reads the user's whole
     * meal history, which is what the readiness count needs.
     *
     * @return array<string, true> keyed `Y-m-d`, so membership is a hash lookup
     */
    public function mealDays(User $user, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        $bounds = app(ResolveDayBounds::class);
        $resolveDay = app(ResolveUserDay::class);

        $query = Meal::query()->where('user_id', $user->getKey());

        if ($start !== null) {
            $query->where('eaten_at', '>=', $bounds($user, $start)->startsAt);
        }

        if ($end !== null) {
            $query->where('eaten_at', '<', $bounds($user, $end)->endsAt);
        }

        $days = [];
        $meals = $query->orderBy('eaten_at')->get(['id', 'eaten_at']);

        foreach ($meals as $meal) {
            $days[$resolveDay($user, $meal->eaten_at)->toDateString()] = true;
        }

        return $days;
    }

    /**
     * How many days each of the user's conditions has that the engine can
     * actually compare — a rating for that condition *and* a logged meal.
     *
     * This is the quantity `ComputeCorrelations` gates on, so readiness has to
     * count it too: a meter reading "ready" against a gate that refuses would
     * drop the condition off the insights page entirely, appearing in neither
     * the waiting list nor the ranking.
     *
     * @return array<int, int> keyed by condition id; a condition with none is absent
     */
    public function comparableDayCounts(User $user): array
    {
        $mealDays = array_keys($this->mealDays($user));

        if ($mealDays === []) {
            return [];
        }

        $logs = ConditionLog::query()
            ->where('user_id', $user->getKey())
            ->whereIn('date', $mealDays)
            ->get(['id', 'condition_id']);

        $counts = [];

        foreach ($logs as $log) {
            $counts[$log->condition_id] = ($counts[$log->condition_id] ?? 0) + 1;
        }

        return $counts;
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
