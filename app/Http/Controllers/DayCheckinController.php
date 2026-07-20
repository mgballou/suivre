<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Journal\RecordDailyCheckinRequest;
use App\Models\User;
use App\Services\Journal\Actions\RecordDailyCheckin;
use Illuminate\Http\RedirectResponse;

class DayCheckinController extends Controller
{
    public function __construct(
        private readonly RecordDailyCheckin $recordDailyCheckin,
    ) {}

    /**
     * Persist one day's check-in.
     *
     * The record is written through the authenticated user's relation, so a
     * request can only ever reach its own row — the route parameter names a
     * date, never someone else's check-in.
     *
     * Redirecting to the day re-renders it with its saved state, which is what
     * makes the day's colour arrive (D20) rather than being announced.
     */
    public function __invoke(RecordDailyCheckinRequest $request, string $date): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        ($this->recordDailyCheckin)(
            user: $user,
            date: $request->checkinDate(),
            mood: $request->mood(),
            sleep: $request->sleep(),
            stress: $request->stress(),
            note: $request->note(),
        );

        return to_route('day', ['date' => $date]);
    }
}
