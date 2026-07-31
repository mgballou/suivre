<?php

declare(strict_types=1);

namespace App\Services\Insights\Actions;

use App\Enums\RampStep;
use App\Models\User;
use App\Services\Insights\CorrelationDataRepository;
use App\Services\Insights\Data\ExposureTimeline;
use App\Services\Insights\Data\SuspectTag;
use App\Services\Insights\Data\TimelineDay;
use App\Services\Insights\Data\TimelineTag;
use App\Services\Journal\DailyIntensityRepository;
use Carbon\CarbonImmutable;

/**
 * Lays a user's condition intensity and their tag exposures over the same run of
 * days, aligned column for column.
 *
 * Days come from the user's own calendar — `exposureHistory` resolves a meal to
 * the day the user was living rather than the UTC one — so a late supper marks
 * the column the user would point at.
 */
class BuildExposureTimeline
{
    /**
     * Ranges the timeline offers.
     *
     * A month is what someone reads about their own recent life; ninety days is
     * the span the correlation engine measures over, so the two views are "what
     * I remember" and "what the ranking saw". Nothing longer: past a quarter the
     * columns are too thin to read a co-occurrence off.
     *
     * @var array<int, int>
     */
    public const array RANGES = [30, 90];

    /**
     * How many tag rows the timeline draws.
     *
     * The point of the surface is spotting two rows that mark the same columns,
     * and that comparison collapses once there are more rows than the eye can
     * hold at once.
     */
    private const int MAX_TAGS = 8;

    public function __invoke(User $user, CarbonImmutable $today, int $rangeDays): ExposureTimeline
    {
        $rangeDays = in_array($rangeDays, self::RANGES, strict: true)
            ? $rangeDays
            : self::RANGES[0];

        $end = $today->startOfDay();
        $start = $end->subDays($rangeDays - 1);

        $ratings = app(DailyIntensityRepository::class)->worstPerDay($user, $start, $end);
        $history = app(CorrelationDataRepository::class)->exposureHistory($user, $start, $end);

        $days = [];
        $dates = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $dates[] = $date;

            $days[] = new TimelineDay(
                date: $date,
                label: $cursor->format('j M'),
                level: RampStep::fromRating($ratings[$date] ?? null),
            );
        }

        return new ExposureTimeline(
            days: $days,
            tags: $this->tags($history->categoryIdsByDate, $history->tags, $dates),
            rangeDays: $rangeDays,
        );
    }

    /**
     * @param  array<string, array<int, int>>  $categoryIdsByDate
     * @param  array<int, SuspectTag>  $tags
     * @param  array<int, string>  $dates
     * @return array<int, TimelineTag>
     */
    private function tags(array $categoryIdsByDate, array $tags, array $dates): array
    {
        $rows = [];

        foreach ($tags as $categoryId => $tag) {
            $present = array_map(
                static fn (string $date): bool => in_array(
                    $categoryId,
                    $categoryIdsByDate[$date] ?? [],
                    strict: true,
                ),
                $dates,
            );

            $rows[] = new TimelineTag(
                name: $tag->name,
                slug: $tag->slug,
                present: $present,
            );
        }

        // Most frequent first: the rows worth comparing against each other end
        // up adjacent, which is the whole point of drawing them together.
        usort(
            $rows,
            static fn (TimelineTag $a, TimelineTag $b): int => [$b->days(), $a->slug] <=> [$a->days(), $b->slug],
        );

        return array_slice($rows, 0, self::MAX_TAGS);
    }
}
