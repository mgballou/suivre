<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Insights\Actions\BuildConditionInsights;
use App\Services\Insights\Actions\BuildJournalSummary;
use App\Services\Insights\Data\ConditionInsight;
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

            /*
             * Deferred because ranking is the expensive part of this page: the
             * engine walks the user's whole history and shuffles each tag's
             * occurrences up to sixty times to estimate its noise band. The
             * descriptive summary above is complete on its own and paints
             * immediately; the ranking arrives on a second request.
             */
            'insights' => Inertia::defer(fn (): array => app(BuildConditionInsights::class)($user)
                ->map(fn (ConditionInsight $insight): array => $insight->toArray())
                ->all()),
        ]);
    }
}
