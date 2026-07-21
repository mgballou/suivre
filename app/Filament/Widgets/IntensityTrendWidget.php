<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Journal\Actions\BuildIntensityTrend;
use App\Services\Journal\Data\IntensityPoint;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * The backstage half of the charting standard (D20): the user app authors its
 * charts in Recharts, the backstage *configures* Filament's stock widget. Two
 * libraries, one design system — this widget shares no code with the React
 * chart, only tokens, and exists to prove they still read as one instrument.
 *
 * The petrol step is `#4e8483` rather than the app's light-mode `#3f7d7b`
 * because Chart.js bakes colours in at render and Filament does not re-render a
 * widget on a theme toggle. That step is the one rung of the ramp clearing 3:1
 * against both Filament surfaces (4.24:1 light, 4.38:1 dark), so a single hex
 * stays AA in both modes without a theme-aware redraw.
 */
class IntensityTrendWidget extends ChartWidget
{
    private const PETROL = '#4e8483';

    protected ?string $heading = 'Condition intensity';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        return 'Worst daily rating over the last 30 days. A gap is a day with no entry, not a zero.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return ['datasets' => [], 'labels' => []];
        }

        $trend = app(BuildIntensityTrend::class)(
            user: $user,
            today: CarbonImmutable::now(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Condition intensity',
                    'data' => array_map(
                        static fn (IntensityPoint $point): ?int => $point->intensity,
                        $trend->points,
                    ),
                    'borderColor' => self::PETROL,
                    'backgroundColor' => self::PETROL . '1a',
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'tension' => 0.3,
                    'fill' => true,
                    'spanGaps' => false,
                ],
            ],
            'labels' => array_map(
                static fn (IntensityPoint $point): string => $point->label,
                $trend->points,
            ),
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
                    'max' => 10,
                    'ticks' => ['stepSize' => 2],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
