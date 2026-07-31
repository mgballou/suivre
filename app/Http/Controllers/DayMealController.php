<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Meals\StoreMealRequest;
use App\Models\User;
use App\Services\Meals\Actions\LogMeal;
use Illuminate\Http\RedirectResponse;

class DayMealController extends Controller
{
    /**
     * Save a meal against a day.
     *
     * The client sends no timestamp — it never derives a date — so `LogMeal`
     * resolves the instant from the day and the meal's slot.
     */
    public function __invoke(StoreMealRequest $request, string $date): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        app(LogMeal::class)(
            user: $user,
            date: $request->mealDate(),
            mealType: $request->mealType(),
            entries: $request->entries(),
        );

        return to_route('day', ['date' => $date]);
    }
}
