<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DayController extends Controller
{
    /**
     * Render the day shell. SUI-7 fills the two-tap check-in flow.
     *
     * The route constrains {date} to YYYY-MM-DD.
     */
    public function __invoke(Request $request, string $date): Response
    {
        return Inertia::render('day', [
            'date' => $date,
        ]);
    }
}
