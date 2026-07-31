<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Conditions\RateConditionRequest;
use App\Models\Condition;
use App\Models\User;
use App\Services\Conditions\Actions\RateCondition;
use Illuminate\Http\RedirectResponse;

class DayConditionController extends Controller
{
    /**
     * Record one condition's 0–10 rating for a day.
     *
     * One tap writes: the picker has no save button, so redirecting back to the
     * day re-renders it with the saved state and lets the day's colour arrive
     * (D20) rather than being announced.
     */
    public function __invoke(RateConditionRequest $request, string $date, Condition $condition): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        app(RateCondition::class)(
            user: $user,
            condition: $condition,
            date: $request->ratingDate(),
            intensity: $request->intensity(),
        );

        return to_route('day', ['date' => $date]);
    }
}
