<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DayCheckinController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InsightsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('calendar/{month?}', CalendarController::class)
        ->where('month', '[0-9]{4}-[0-9]{2}')
        ->name('calendar');

    Route::prefix('day/{date}')
        ->where(['date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])
        ->group(function () {
            Route::get('/', DayController::class)->name('day');
            Route::post('checkin', DayCheckinController::class)->name('day.checkin');
        });

    Route::get('insights', InsightsController::class)->name('insights');
});

require __DIR__ . '/settings.php';
