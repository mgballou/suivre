<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Insights\Actions\BuildJournalSummary;
use App\Services\Journal\Actions\BuildIntensityMonth;
use App\Services\Journal\Actions\BuildIntensityTrend;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    /**
     * Render the insights surface.
     *
     * Descriptive throughout: what the user logged, and how far each condition
     * is from the volume the correlation engine needs. The ranked suspects
     * (SUI-22) will be added above this, not in place of it — a user who has
     * read their own month for three months does not stop wanting it the day a
     * ranking appears.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $today = app(ResolveUserDay::class)($user, CarbonImmutable::now());

        return Inertia::render('insights', [
            'trend' => app(BuildIntensityTrend::class)($user, $today)->toArray(),
            'month' => app(BuildIntensityMonth::class)($user, $today)->toArray(),
            'summary' => app(BuildJournalSummary::class)($user, $today)->toArray(),
        ]);
    }
}
