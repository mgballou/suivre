<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Journal\Actions\BuildDayView;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DayController extends Controller
{
    public function __construct(
        private readonly ResolveUserDay $resolveUserDay,
        private readonly BuildDayView $buildDayView,
    ) {}

    /**
     * Render one day's check-in surface.
     *
     * The route constrains {date} to YYYY-MM-DD; this additionally rejects
     * dates that match the format but do not exist (e.g. 2026-02-31), which
     * would otherwise reach Carbon and roll over into a neighbouring month.
     */
    public function __invoke(Request $request, string $date): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->isRealDate($date), 404);

        $today = ($this->resolveUserDay)($user, CarbonImmutable::now());

        return Inertia::render(
            'day',
            ($this->buildDayView)($user, CarbonImmutable::parse($date), $today)->toArray(),
        );
    }

    private function isRealDate(string $date): bool
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));

        return checkdate($month, $day, $year);
    }
}
