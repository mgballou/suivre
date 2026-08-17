<?php

declare(strict_types=1);

namespace App\Services\Journal\Actions;

use App\Enums\FlareIntensity;
use App\Enums\MealType;
use App\Enums\MoodLevel;
use App\Enums\RampStep;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\DailyCheckin;
use App\Models\FlareEvent;
use App\Models\Meal;
use App\Models\Scopes\ActiveScope;
use App\Models\User;
use App\Services\Journal\Data\DayCondition;
use App\Services\Journal\Data\DayFlare;
use App\Services\Journal\Data\DayMeal;
use App\Services\Journal\Data\DaySection;
use App\Services\Journal\Data\DayView;
use App\Services\Journal\Data\MealTypeOption;
use App\Services\Journal\Data\ScaleOption;
use Carbon\CarbonImmutable;

class BuildDayView
{
    /**
     * Assemble the day's journal surface for one user.
     *
     * `$today` is the user's local day (ResolveUserDay), never the server's.
     *
     * The day's ratings are read in one query and matched against the active
     * conditions in memory, so the screen costs the same whether the user
     * tracks one condition or ten.
     */
    public function __invoke(User $user, CarbonImmutable $date, CarbonImmutable $today): DayView
    {
        $checkin = $user->dailyCheckins()
            ->where('date', $date->toDateString())
            ->first();

        $conditions = $this->conditions($user, $date);
        $flares = $this->flares($user, $date);
        $meals = $this->meals($user, $date);
        $sections = $this->sections($checkin, $conditions, $meals, $flares);

        return new DayView(
            date: $date->toDateString(),
            label: $date->format('l j F Y'),
            month: $date->format('Y-m'),
            level: $this->level($conditions, $checkin),
            isToday: $date->toDateString() === $today->toDateString(),
            mood: $checkin?->mood?->value,
            sleep: $checkin?->sleep?->value,
            stress: $checkin?->stress?->value,
            note: $checkin?->note,
            scales: [
                'mood' => $this->options(MoodLevel::ordered()),
                'sleep' => $this->options(SleepQuality::ordered()),
                'stress' => $this->options(StressLevel::ordered()),
            ],
            conditions: $conditions,
            flares: $flares,
            flareIntensities: $this->options(FlareIntensity::ordered()),
            meals: $meals,
            mealTypes: $this->mealTypeOptions(),
            sections: $sections,
            openSection: $this->openSection($sections),
        );
    }

    /**
     * The day's four cards, each stating what it holds.
     *
     * Every summary is flat description — "3 items", "None", "Not recorded".
     * None of them counts toward a total and none of them is a nudge: a journal
     * that scores the day stops being a journal (D20/D28).
     *
     * @param  array<int, DayCondition>  $conditions
     * @param  array<int, DayMeal>  $meals
     * @param  array<int, DayFlare>  $flares
     * @return array<int, DaySection>
     */
    private function sections(?DailyCheckin $checkin, array $conditions, array $meals, array $flares): array
    {
        $rated = count(array_filter(
            $conditions,
            static fn (DayCondition $condition): bool => $condition->intensity !== null,
        ));

        $items = array_sum(array_map(
            static fn (DayMeal $meal): int => count($meal->entries),
            $meals,
        ));

        return [
            new DaySection(
                key: 'checkin',
                title: 'Check-in',
                summary: $checkin instanceof DailyCheckin
                    ? ($checkin->sleep?->getLabel() ?? 'Recorded')
                    : 'Not recorded',
                recorded: $checkin instanceof DailyCheckin,
            ),
            new DaySection(
                key: 'conditions',
                title: 'Conditions',
                summary: match (true) {
                    $conditions === [] => 'None tracked',
                    $rated > 0 => "{$rated} rated",
                    default => 'Not rated',
                },
                recorded: $rated > 0,
            ),
            new DaySection(
                key: 'meals',
                title: 'Meals',
                summary: $items > 0
                    ? $items . ' ' . str('item')->plural($items)->value()
                    : 'Nothing logged',
                recorded: $meals !== [],
            ),
            new DaySection(
                key: 'flares',
                title: 'Flares',
                summary: $flares === []
                    ? 'None'
                    : count($flares) . ' ' . str('flare')->plural(count($flares))->value(),
                recorded: $flares !== [],
            ),
        ];
    }

    /**
     * The card the day opens on: the first thing not yet on file.
     *
     * Guidance without a wizard — a fresh day lands on the check-in, a partly
     * filled one on the gap, and a reviewed one on nothing at all. Flares are
     * excluded because an absent flare is a complete answer, not a gap, and a
     * card that stayed open on every quiet day would read as a demand.
     *
     * @param  array<int, DaySection>  $sections
     */
    private function openSection(array $sections): ?string
    {
        foreach ($sections as $section) {
            if ($section->key !== 'flares' && ! $section->recorded) {
                return $section->key;
            }
        }

        return null;
    }

    /**
     * The day's meals, ordered as they were eaten.
     *
     * Day membership is resolved through the same bounds the correlation
     * engine uses, so a meal eaten at 00:30 belongs to the day the user was
     * living rather than the UTC one (D5).
     *
     * @return array<int, DayMeal>
     */
    private function meals(User $user, CarbonImmutable $date): array
    {
        $bounds = app(ResolveDayBounds::class)($user, $date);

        return $user->meals()
            ->with('entries.foodItem.categories')
            ->where('eaten_at', '>=', $bounds->startsAt)
            ->where('eaten_at', '<', $bounds->endsAt)
            ->orderBy('eaten_at')
            ->get()
            ->map(static fn (Meal $meal): DayMeal => DayMeal::fromMeal($meal, $user->timezone))
            ->all();
    }

    /**
     * @return array<int, MealTypeOption>
     */
    private function mealTypeOptions(): array
    {
        return array_map(
            static fn (MealType $type): MealTypeOption => MealTypeOption::fromMealType($type),
            MealType::cases(),
        );
    }

    /**
     * The day's worst rating, or step 1 for a day that was checked into but
     * carries no rating — an entry exists, so the cell must not read as empty.
     *
     * @param  array<int, DayCondition>  $conditions
     */
    private function level(array $conditions, ?DailyCheckin $checkin): int
    {
        $worst = max([RampStep::None->value, ...array_map(
            static fn (DayCondition $condition): int => $condition->level,
            $conditions,
        )]);

        if ($worst > RampStep::None->value) {
            return $worst;
        }

        return $checkin instanceof DailyCheckin ? RampStep::Barely->value : RampStep::None->value;
    }

    /**
     * @return array<int, DayCondition>
     */
    private function conditions(User $user, CarbonImmutable $date): array
    {
        $logs = $user->conditionLogs()
            ->where('date', $date->toDateString())
            ->get()
            ->keyBy(static fn (ConditionLog $log): int => $log->condition_id);

        return $user->conditions()
            ->tap(new ActiveScope())
            ->orderBy('name')
            ->get()
            ->map(static fn (Condition $condition): DayCondition => DayCondition::fromCondition(
                $condition,
                $logs->get($condition->id),
            ))
            ->all();
    }

    /**
     * @return array<int, DayFlare>
     */
    private function flares(User $user, CarbonImmutable $date): array
    {
        $bounds = app(ResolveDayBounds::class)($user, $date);

        return $user->flareEvents()
            ->with('condition')
            ->where('occurred_at', '>=', $bounds->startsAt)
            ->where('occurred_at', '<', $bounds->endsAt)
            ->orderBy('occurred_at')
            ->get()
            ->map(static fn (FlareEvent $flare): DayFlare => DayFlare::fromFlareEvent($flare, $user->timezone))
            ->all();
    }

    /**
     * @param  array<int, FlareIntensity|MoodLevel|SleepQuality|StressLevel>  $cases
     * @return array<int, ScaleOption>
     */
    private function options(array $cases): array
    {
        return array_map(
            static fn (FlareIntensity|MoodLevel|SleepQuality|StressLevel $case): ScaleOption => new ScaleOption(
                value: $case->value,
                label: $case->getLabel(),
            ),
            $cases,
        );
    }
}
