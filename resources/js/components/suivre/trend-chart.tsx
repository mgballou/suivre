import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    ReferenceDot,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    CHART_AREA_OPACITY,
    CHART_AXIS,
    CHART_GRID,
    CHART_LINE,
    chartConfig,
    seriesOf,
    type ChartIntent,
} from '@/lib/charts';
import { cn } from '@/lib/utils';

export type TrendPoint = {
    label: string;
    values: Record<string, number | null>;
};

type TrendChartProps = {
    intent: ChartIntent;
    data: TrendPoint[];
    yDomain?: [number, number];
    className?: string;
};

/**
 * Change over time, on one y-axis. A dual axis is never an option here — two
 * measures of different scale become two charts.
 *
 * A magnitude chart is one line over a 10%-opacity wash with its endpoint
 * marked and labelled, and carries no legend: there is one colour, and the
 * caller's heading already names it. An identity chart always carries a
 * legend, because two of the four slots sit under 3:1 on the light surface and
 * one dark pair lands in the 6–8 CVD band — identity must never rest on hue
 * alone.
 */
export function TrendChart({
    intent,
    data,
    yDomain,
    className,
}: TrendChartProps) {
    const config = chartConfig(intent);
    const series = seriesOf(intent);
    const showLegend = intent.kind === 'identity';

    const rows = data.map((point) => ({ label: point.label, ...point.values }));

    const lastPoint = data.at(-1);
    const endpoint =
        intent.kind === 'magnitude' && lastPoint
            ? lastPoint.values[intent.series.key]
            : null;

    return (
        <ChartContainer
            config={config}
            className={cn('aspect-auto h-56 w-full', className)}
        >
            <ComposedChart accessibilityLayer data={rows}>
                <CartesianGrid {...CHART_GRID} />

                <XAxis {...CHART_AXIS} dataKey="label" interval="preserveStartEnd" />
                <YAxis {...CHART_AXIS} domain={yDomain} width={32} allowDecimals={false} />

                <ChartTooltip
                    cursor={{ stroke: 'var(--color-border)', strokeWidth: 1 }}
                    content={<ChartTooltipContent indicator="dot" />}
                />

                {intent.kind === 'magnitude' ? (
                    <Area
                        {...CHART_LINE}
                        dataKey={intent.series.key}
                        name={intent.series.key}
                        type="monotone"
                        stroke={`var(--color-${intent.series.key})`}
                        fill={`var(--color-${intent.series.key})`}
                        fillOpacity={CHART_AREA_OPACITY}
                        connectNulls={false}
                    />
                ) : (
                    series.map((entry) => (
                        <Line
                            {...CHART_LINE}
                            key={entry.key}
                            dataKey={entry.key}
                            name={entry.key}
                            type="monotone"
                            stroke={`var(--color-${entry.key})`}
                            connectNulls={false}
                        />
                    ))
                )}

                {intent.kind === 'magnitude' &&
                    lastPoint &&
                    typeof endpoint === 'number' && (
                        <ReferenceDot
                            x={lastPoint.label}
                            y={endpoint}
                            r={4}
                            fill={`var(--color-${intent.series.key})`}
                            stroke="var(--color-card)"
                            strokeWidth={2}
                            label={{
                                value: String(endpoint),
                                position: 'top',
                                fill: 'var(--color-foreground)',
                                fontSize: 12,
                            }}
                        />
                    )}

                {showLegend && <ChartLegend content={<ChartLegendContent />} />}
            </ComposedChart>
        </ChartContainer>
    );
}
