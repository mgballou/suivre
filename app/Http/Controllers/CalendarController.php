<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Render the calendar shell. SUI-6 fills the month grid.
     *
     * The route constrains {month} to YYYY-MM; this rejects semantically
     * invalid months (e.g. 2026-13) that still match the format.
     */
    public function __invoke(Request $request, ?string $month = null): Response
    {
        if ($month !== null && ! $this->isValidMonth($month)) {
            abort(404);
        }

        return Inertia::render('calendar', [
            'month' => $month,
        ]);
    }

    private function isValidMonth(string $month): bool
    {
        [, $monthPart] = explode('-', $month);
        $monthNumber = (int) $monthPart;

        return $monthNumber >= 1 && $monthNumber <= 12;
    }
}
