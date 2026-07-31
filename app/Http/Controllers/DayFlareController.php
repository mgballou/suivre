<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Conditions\LogFlareEventRequest;
use App\Models\Condition;
use App\Models\User;
use App\Services\Conditions\Actions\LogFlareEvent;
use App\Services\Conditions\Actions\ResolveFlareMoment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class DayFlareController extends Controller
{
    /**
     * Log an acute flare against a day.
     *
     * The client sends no timestamp: it never derives a date, and the instant a
     * flare belongs at depends on whether the day being viewed is the user's
     * today — ResolveFlareMoment owns that call.
     */
    public function __invoke(LogFlareEventRequest $request, string $date, Condition $condition): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        app(LogFlareEvent::class)(
            user: $user,
            condition: $condition,
            occurredAt: app(ResolveFlareMoment::class)($user, $request->flareDate(), CarbonImmutable::now()),
            intensity: $request->intensity(),
            durationMinutes: $request->durationMinutes(),
            note: $request->note(),
        );

        return to_route('day', ['date' => $date]);
    }
}
