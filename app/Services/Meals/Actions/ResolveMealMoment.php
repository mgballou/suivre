<?php

declare(strict_types=1);

namespace App\Services\Meals\Actions;

use App\Enums\MealType;
use App\Models\User;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;

class ResolveMealMoment
{
    /**
     * The instant a meal logged from a day screen is recorded at.
     *
     * A meal stores an instant but is logged from a screen that names a *day*
     * (D5), so the missing time has to come from somewhere. Logging today
     * stamps the actual time. Back-filling an earlier day stamps the slot's
     * conventional hour instead of midnight, which would sit close enough to
     * the boundary to land on the neighbouring day when read back in another
     * timezone.
     *
     * The hours are a convention for ordering a back-filled day's meals, not a
     * claim about when the user ate. Correlation reads the *day*, so the exact
     * hour never reaches an insight.
     */
    public function __invoke(User $user, CarbonImmutable $date, MealType $mealType, CarbonImmutable $now): CarbonImmutable
    {
        $today = app(ResolveUserDay::class)($user, $now);

        $moment = $date->toDateString() === $today->toDateString()
            ? $now
            : CarbonImmutable::parse($date->toDateString(), $user->timezone)
                ->addHours(self::conventionalHour($mealType));

        /*
         * Returned in UTC, and that is load-bearing. Eloquent's datetime cast
         * writes a Carbon's wall-clock reading and then reads it back as the
         * app timezone, so handing it 19:00+12:00 stores the instant 19:00 UTC
         * — the meal silently jumps twelve hours and can land on the wrong day.
         * The user's timezone decides *which* instant; it is not part of it.
         */
        return $moment->utc();
    }

    private static function conventionalHour(MealType $mealType): int
    {
        return match (true) {
            $mealType->isBreakfast() => 8,
            $mealType->isLunch() => 13,
            $mealType->isDinner() => 19,
            default => 16,
        };
    }
}
