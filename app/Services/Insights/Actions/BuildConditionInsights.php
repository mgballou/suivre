<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Models\Condition;
use App\Models\Scopes\ActiveScope;
use App\Models\User;
use App\Services\Insights\Data\ConditionInsight;
use App\Services\Insights\Data\SuspectHint;
use Illuminate\Support\Collection;

/**
 * The ranking each tracked condition has earned, for the conditions that have
 * earned one.
 *
 * A condition below the volume gate is skipped entirely rather than returned
 * with an empty list. The insights page pairs this with `BuildJournalSummary`'s
 * readiness, so every condition appears exactly once — waiting or ranked — and
 * the surface never has to decide which of two contradictory states to believe.
 */
class BuildConditionInsights
{
    /**
     * How many suspects a condition shows.
     *
     * The engine returns every tag it could measure, ordered by lift. Showing
     * all of them would turn a list the data cannot defend into an apparently
     * exhaustive one; five is enough for the shape of the ranking to be visible
     * without inviting the reader down to the rows that are almost certainly
     * noise.
     */
    private const int MAX_SUSPECTS = 5;

    /**
     * @return Collection<int, ConditionInsight>
     */
    public function __invoke(User $user): Collection
    {
        $conditions = $user->conditions()
            ->tap(new ActiveScope())
            ->orderBy('name')
            ->get();

        $insights = [];

        foreach ($conditions as $condition) {
            $insight = $this->insightFor($user, $condition);

            if ($insight !== null) {
                $insights[] = $insight;
            }
        }

        return new Collection($insights);
    }

    private function insightFor(User $user, Condition $condition): ?ConditionInsight
    {
        $report = app(ComputeCorrelations::class)($user, $condition);

        if ($report->status->isInsufficient()) {
            return null;
        }

        $suspects = array_map(
            SuspectHint::fromSuspect(...),
            array_slice($report->suspects(), 0, self::MAX_SUSPECTS),
        );

        return ConditionInsight::fromReport($condition, $report, $suspects);
    }
}
