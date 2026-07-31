<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ConditionActivationController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\DayCheckinController;
use App\Http\Controllers\DayConditionController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\DayFlareController;
use App\Http\Controllers\DayMealController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\MealClassificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\RequireTrackedCondition;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Outside the gate below: this is the screen it redirects to.
    Route::get('onboarding/conditions', [OnboardingController::class, 'show'])->name('onboarding.conditions');
    Route::post('onboarding/conditions', [OnboardingController::class, 'store'])->name('onboarding.conditions.store');

    // Also outside it: managing conditions must work with none active, and it
    // is where a user lands to resume one they had stopped.
    Route::get('settings/conditions', [ConditionController::class, 'index'])->name('conditions.index');
    Route::post('settings/conditions', [ConditionController::class, 'store'])->name('conditions.store');
    Route::patch('settings/conditions/{condition}', [ConditionController::class, 'update'])->name('conditions.update');
    Route::put('settings/conditions/{condition}/activation', ConditionActivationController::class)->name('conditions.activation');

    Route::middleware(RequireTrackedCondition::class)->group(function () {
        Route::get('calendar/{month?}', CalendarController::class)
            ->where('month', '[0-9]{4}-[0-9]{2}')
            ->name('calendar');

        Route::prefix('day/{date}')
            ->where(['date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])
            ->group(function () {
                Route::get('/', DayController::class)->name('day');
                Route::post('checkin', DayCheckinController::class)->name('day.checkin');
                Route::post('conditions/{condition}', DayConditionController::class)->name('day.conditions.rate');
                Route::post('conditions/{condition}/flares', DayFlareController::class)->name('day.conditions.flare');
                Route::post('meals', DayMealController::class)->name('day.meals.store');
            });

        // Not under a day: classifying a draft reads the global catalog and
        // saves nothing, so it needs no date to answer.
        Route::post('meals/classify', MealClassificationController::class)->name('meals.classify');

        Route::get('insights', InsightsController::class)->name('insights');
    });
});

require __DIR__ . '/settings.php';
