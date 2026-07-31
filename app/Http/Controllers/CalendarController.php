<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Journal\Actions\BuildCalendarMonth;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Render the month grid — the app's front door.
     *
     * The route constrains {month} to YYYY-MM; this additionally rejects
     * semantically invalid months (e.g. 2026-13) that still match the format.
     * With no month the user's current local month is shown.
     */
    public function __invoke(Request $request, ?string $month = null): Response
    {
        /** @var User $user */
        $user = $request->user();

        $today = app(ResolveUserDay::class)($user, CarbonImmutable::now());

        if ($month !== null && ! $this->isValidMonth($month)) {
            abort(404);
        }

        $requested = $month === null
            ? $today->startOfMonth()
            : CarbonImmutable::parse($month . '-01');

        return Inertia::render(
            'calendar',
            app(BuildCalendarMonth::class)($user, $requested, $today)->toArray(),
        );
    }

    private function isValidMonth(string $month): bool
    {
        [, $monthPart] = explode('-', $month);
        $monthNumber = (int) $monthPart;

        return $monthNumber >= 1 && $monthNumber <= 12;
    }
}
