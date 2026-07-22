<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Platform\PlatformMetricsRepository;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * The backstage half of the charting standard (D20): the user app authors its
 * charts in Recharts, the backstage *configures* Filament's stock widget. Two
 * libraries, one design system — this widget shares no code with the React
 * chart, only tokens, and exists to prove they still read as one instrument.
 *
 * An operator has no journal of their own, so the backstage shows a *cross-user*
 * activity metric — condition entries logged across all users — not one person's
 * intensity. It answers how much the app is being used, which is what oversight
 * actually needs.
 *
 * The petrol bar is `#4e8483` rather than the app's light-mode `#3f7d7b` because
 * Chart.js bakes colours in at render and Filament does not re-render a widget on
 * a theme toggle. That step is the one rung of the ramp clearing 3:1 against both
 * Filament surfaces (4.24:1 light, 4.38:1 dark), so a single hex stays AA in both
 * modes without a theme-aware redraw.
 */
class EntriesLoggedWidget extends ChartWidget
{
    private const PETROL = '#4e8483';

    private const WINDOW_DAYS = 30;

    protected ?string $heading = 'Entries logged';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        return 'Condition entries logged across all users over the last 30 days.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $end = CarbonImmutable::now()->startOfDay();
        $start = $end->subDays(self::WINDOW_DAYS - 1);

        $counts = app(PlatformMetricsRepository::class)->entriesLoggedPerDay($start, $end);

        $data = [];
        $labels = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();

            $data[] = $counts[$date] ?? 0;
            $labels[] = $cursor->format('j M');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Entries logged',
                    'data' => $data,
                    'backgroundColor' => self::PETROL,
                    'borderColor' => self::PETROL,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * One series on one axis, so the legend would only restate the heading.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
