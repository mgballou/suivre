<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DayCheckinController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('calendar/{month?}', CalendarController::class)
        ->where('month', '[0-9]{4}-[0-9]{2}')
        ->name('calendar');

    Route::get('day/{date}', DayController::class)
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('day');

    Route::post('day/{date}/checkin', DayCheckinController::class)
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('day.checkin');

    Route::inertia('insights', 'insights')->name('insights');
});

require __DIR__ . '/settings.php';
