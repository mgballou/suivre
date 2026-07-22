import { Head } from '@inertiajs/react';
import {
    CalendarHeatmap,
    type CalendarHeatmapProps,
} from '@/components/suivre/calendar-heatmap';
import { TrendChart, type TrendPoint } from '@/components/suivre/trend-chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type InsightsProps = {
    trend: {
        points: TrendPoint[];
        loggedDays: number;
    };
    month: Omit<CalendarHeatmapProps, 'className'>;
};

/** The scale a daily condition rating is recorded on. */
const INTENSITY_DOMAIN: [number, number] = [0, 10];

const TREND_WINDOW_DAYS = 30;

export default function Insights({ trend, month }: InsightsProps) {
    const latest = trend.points.findLast(
        (point) => point.values.intensity !== null,
    );

    return (
        <>
            <Head title="Insights" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-lg font-semibold tracking-tight">
                        Insights
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Lag-aware correlations arrive in E4. Suggestive, never
                        proof.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Condition intensity</CardTitle>
                        <CardDescription>
                            Your worst rating each day. {trend.loggedDays} of{' '}
                            {TREND_WINDOW_DAYS} days logged.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {latest && (
                            <p className="flex items-baseline gap-2">
                                <span className="text-4xl font-semibold leading-none">
                                    {latest.values.intensity}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    on {latest.label}
                                </span>
                            </p>
                        )}

                        <TrendChart
                            intent={{
                                kind: 'magnitude',
                                series: {
                                    key: 'intensity',
                                    label: 'Condition intensity',
                                },
                            }}
                            data={trend.points}
                            yDomain={INTENSITY_DOMAIN}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Intensity by day</CardTitle>
                        <CardDescription>
                            The same ramp the calendar climbs — depth, not
                            damage.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CalendarHeatmap {...month} className="max-w-xs" />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
