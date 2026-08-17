import { Deferred, Head, router } from '@inertiajs/react';
import {
    CalendarHeatmap,
    type CalendarHeatmapProps,
} from '@/components/suivre/calendar-heatmap';
import {
    ExposureTimeline,
    type ExposureTimelineData,
} from '@/components/suivre/exposure-timeline';
import {
    InsightReadiness,
    type ConditionReadiness,
} from '@/components/suivre/insight-readiness';
import { LoggedTags, type LoggedTag } from '@/components/suivre/logged-tags';
import {
    SuspectList,
    type ConditionInsight,
} from '@/components/suivre/suspect-list';
import { TrendChart, type TrendPoint } from '@/components/suivre/trend-chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type InsightsProps = {
    trend: {
        points: TrendPoint[];
        loggedDays: number;
        windowDays: number;
    };
    month: Omit<CalendarHeatmapProps, 'className'>;
    summary: {
        conditions: ConditionReadiness[];
        tags: LoggedTag[];
    };
    timeline: ExposureTimelineData;
    timelineRanges: number[];
    /** Deferred: absent on the first render, present on the follow-up request. */
    insights?: ConditionInsight[];
};

/** The scale a daily condition rating is recorded on. */
const INTENSITY_DOMAIN: [number, number] = [0, 10];

/**
 * The insights surface, which is descriptive for the first three months and
 * stays descriptive afterwards.
 *
 * The SUI-36 spike put the earliest honest ranking at around ninety days of
 * ratings. Rather than leave that stretch empty — the likeliest moment for
 * someone to give up — the page reads the user their own record: how the
 * condition moved, which days carry data, what they ate most. When the ranked
 * suspects arrive (SUI-22) they stack above this; nothing here is taken away.
 */
export default function Insights({
    trend,
    month,
    summary,
    timeline,
    timelineRanges,
    insights,
}: InsightsProps) {
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
                        What you have logged, and what it can honestly say.
                    </p>
                </div>

                <Deferred
                    data="insights"
                    fallback={
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-14 w-full" />
                        </div>
                    }
                >
                    <SuspectList insights={insights ?? []} />
                </Deferred>

                <InsightReadiness conditions={summary.conditions} />

                <Card className="elevation-raised">
                    <CardHeader>
                        <CardTitle>Condition intensity</CardTitle>
                        <CardDescription>
                            Your worst rating each day. {trend.loggedDays} of{' '}
                            {trend.windowDays} days logged.
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

                <Card className="elevation-raised">
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

                <Card className="elevation-raised">
                    <CardHeader>
                        <CardTitle>Foods against days</CardTitle>
                        <CardDescription>
                            The same days on every row. Tags that mark the same
                            columns travel together — which is why a ranking
                            cannot tell them apart.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ExposureTimeline
                            {...timeline}
                            ranges={timelineRanges}
                            onRangeChange={(range) =>
                                router.reload({
                                    only: ['timeline'],
                                    data: { range },
                                })
                            }
                        />
                    </CardContent>
                </Card>

                <Card className="elevation-raised">
                    <CardHeader>
                        <CardTitle>What you ate most</CardTitle>
                        <CardDescription>
                            Counts over the last {trend.windowDays} days. A
                            description of your diet, not a verdict on it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LoggedTags
                            tags={summary.tags}
                            windowDays={trend.windowDays}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
