<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Journal\Actions\BuildIntensityMonth;
use App\Services\Journal\Actions\BuildIntensityTrend;
use App\Services\Journal\Actions\ResolveUserDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function __construct(
        private readonly ResolveUserDay $resolveUserDay,
        private readonly BuildIntensityTrend $buildIntensityTrend,
        private readonly BuildIntensityMonth $buildIntensityMonth,
    ) {}

    /**
     * Render the charting reference surface.
     *
     * Lag-aware correlations arrive with the insights UI; what this page proves
     * now is that a chart and a heatmap declared against the shared tokens
     * render as one system in both modes.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $today = ($this->resolveUserDay)($user, CarbonImmutable::now());

        return Inertia::render('insights', [
            'trend' => ($this->buildIntensityTrend)($user, $today)->toArray(),
            'month' => ($this->buildIntensityMonth)($user, $today)->toArray(),
        ]);
    }
}
