<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Models\Condition;
use App\Models\Scopes\ActiveScope;
use App\Models\User;
use App\Services\Insights\CorrelationDataRepository;
use App\Services\Insights\CorrelationThresholds;
use App\Services\Insights\Data\ConditionReadiness;
use App\Services\Insights\Data\JournalSummary;
use App\Services\Insights\Data\LoggedTag;
use App\Services\Journal\Actions\BuildIntensityTrend;
use Carbon\CarbonImmutable;

/**
 * Assembles the descriptive summary the insights surface always carries.
 *
 * Deliberately makes no comparison. Every figure here is a count of something
 * the user did — days rated, days a tag appeared — and none of them is set
 * against another. That restraint is the whole design: the surface has to be
 * worth reading for three months without once implying a trigger, because
 * implying one before the data supports it is the failure mode the 90-day gate
 * exists to prevent.
 */
class BuildJournalSummary
{
    /**
     * How many tags the "what you logged most" list names.
     *
     * Short on purpose. A full ranked list of every category invites the reader
     * to treat the order as meaningful, and it is not — it is how often they ate
     * something, nothing more.
     */
    private const int TOP_TAGS = 6;

    public function __invoke(
        User $user,
        CarbonImmutable $today,
        int $windowDays = BuildIntensityTrend::DEFAULT_WINDOW_DAYS,
    ): JournalSummary {
        $end = $today->startOfDay();
        $start = $end->subDays($windowDays - 1);

        return new JournalSummary(
            conditions: $this->readiness($user),
            tags: $this->loggedTags($user, $start, $end),
        );
    }

    /**
     * Readiness for each condition the user is currently tracking.
     *
     * Stopped conditions are left out: they keep their history, but they are not
     * accumulating, so a progress figure against them would only ever describe a
     * wait that is not happening.
     *
     * @return array<int, ConditionReadiness>
     */
    private function readiness(User $user): array
    {
        return $user->conditions()
            ->tap(new ActiveScope())
            ->withCount('conditionLogs')
            ->orderByDesc('condition_logs_count')
            ->orderBy('id')
            ->get()
            ->map(static fn (Condition $condition): ConditionReadiness => new ConditionReadiness(
                id: $condition->id,
                name: $condition->name,
                hue: $condition->color->value,
                loggedDays: (int) $condition->condition_logs_count,
                requiredDays: CorrelationThresholds::MINIMUM_LOGGED_DAYS,
            ))
            ->all();
    }

    /**
     * The tags that turned up on the most days in the window.
     *
     * Counted through the same `exposureHistory` the correlation engine reads,
     * so what the user is shown they logged is exactly what the engine will
     * eventually correlate — including its silences. An entry still awaiting
     * classification carries no tags, so a user whose foods are all unmatched
     * sees an empty list, which is the truth and is worth them seeing.
     *
     * @return array<int, LoggedTag>
     */
    private function loggedTags(User $user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $history = app(CorrelationDataRepository::class)->exposureHistory($user, $start, $end);

        $daysByCategory = [];

        foreach ($history->categoryIdsByDate as $categoryIds) {
            foreach ($categoryIds as $categoryId) {
                $daysByCategory[$categoryId] = ($daysByCategory[$categoryId] ?? 0) + 1;
            }
        }

        // Sorted by id first so that tags appearing on the same number of days
        // keep a stable order between requests — PHP's sort is stable, and an
        // ordering that reshuffles on every render reads as meaning it lacks.
        ksort($daysByCategory);
        arsort($daysByCategory);

        $tags = [];

        foreach (array_slice($daysByCategory, 0, self::TOP_TAGS, preserve_keys: true) as $categoryId => $days) {
            $tag = $history->tags[$categoryId];

            $tags[] = new LoggedTag(name: $tag->name, slug: $tag->slug, days: $days);
        }

        return $tags;
    }
}
