import { INTENSITY_BG, INTENSITY_LABELS } from '@/lib/intensity';
import { cn } from '@/lib/utils';
import type { IntensityLevel, IsoDate } from '@/types';

export type TimelineDay = {
    date: IsoDate;
    label: string;
    level: IntensityLevel;
};

export type TimelineTag = {
    name: string;
    slug: string;
    /** Index-aligned to the timeline's days — one entry per column, in order. */
    present: boolean[];
    days: number;
};

export type ExposureTimelineData = {
    days: TimelineDay[];
    tags: TimelineTag[];
    rangeDays: number;
};

type ExposureTimelineProps = ExposureTimelineData & {
    ranges: number[];
    onRangeChange: (rangeDays: number) => void;
};

/**
 * Intensity and tag exposure on one axis, so a pattern can be looked at rather
 * than taken on trust.
 *
 * The ranked suspects say which tags rose together; this says whether they ever
 * moved apart. Two tag rows marking the same columns is a confound the ranking
 * cannot resolve at personal scale, and seeing it is the honest correction to
 * reading a list as findings.
 *
 * Marks are the ramp's own hue at full strength rather than a second colour: the
 * page already spends its one hue on intensity, and a new one here would imply
 * a relationship the row does not claim.
 */
export function ExposureTimeline({
    days,
    tags,
    rangeDays,
    ranges,
    onRangeChange,
}: ExposureTimelineProps) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center gap-1">
                {ranges.map((range) => (
                    <button
                        key={range}
                        type="button"
                        onClick={() => onRangeChange(range)}
                        aria-pressed={range === rangeDays}
                        className={cn(
                            'min-h-9 rounded-md px-3 text-xs font-medium transition-colors duration-[var(--dur-micro)] ease-quiet',
                            range === rangeDays
                                ? 'bg-accent text-foreground'
                                : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                        )}
                    >
                        {range} days
                    </button>
                ))}
            </div>

            {days.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nothing logged in this range yet.
                </p>
            ) : (
                /*
                 * Ninety columns will not fit a phone, and squeezing them until
                 * they do is what makes a co-occurrence unreadable. The table
                 * keeps its own width and scrolls; the page never does.
                 */
                <div className="overflow-x-auto">
                    <div className="flex min-w-max flex-col gap-1">
                        <Row label="Intensity" labelClassName="font-medium">
                            {days.map((day) => (
                                <span
                                    key={day.date}
                                    role="listitem"
                                    data-date={day.date}
                                    data-level={day.level}
                                    title={`${day.label} — ${INTENSITY_LABELS[day.level]}`}
                                    aria-label={`${day.label}, ${INTENSITY_LABELS[day.level]}`}
                                    className={cn(
                                        'h-4 w-2 rounded-xs ring-1 ring-inset ring-border',
                                        INTENSITY_BG[day.level],
                                    )}
                                />
                            ))}
                        </Row>

                        {tags.map((tag) => (
                            <Row
                                key={tag.slug}
                                label={tag.name}
                                trailing={`${tag.days}`}
                            >
                                {tag.present.map((present, index) => (
                                    <span
                                        key={days[index].date}
                                        role="listitem"
                                        data-date={days[index].date}
                                        data-present={present ? '' : undefined}
                                        title={`${tag.name} — ${days[index].label}`}
                                        aria-label={`${days[index].label}, ${
                                            present ? tag.name : `no ${tag.name}`
                                        }`}
                                        className={cn(
                                            /*
                                             * Presence is carried by the ramp's
                                             * darkest step against muted — a far
                                             * bigger step than any two adjacent
                                             * ramp levels, so the binary reads at
                                             * two pixels wide where the ramp
                                             * itself needs its hairline ring.
                                             */
                                            'h-4 w-2 rounded-xs',
                                            present
                                                ? 'bg-intensity-5'
                                                : 'bg-muted',
                                        )}
                                    />
                                ))}
                            </Row>
                        ))}
                    </div>
                </div>
            )}

            {tags.length === 0 && days.length > 0 && (
                <p className="text-sm text-muted-foreground">
                    No meals in this range have been matched to the food
                    catalogue, so there is nothing to lay against the ramp yet.
                </p>
            )}
        </div>
    );
}

type RowProps = {
    label: string;
    labelClassName?: string;
    trailing?: string;
    children: React.ReactNode;
};

function Row({ label, labelClassName, trailing, children }: RowProps) {
    return (
        <div className="flex items-center gap-3">
            <span
                className={cn(
                    'sticky left-0 w-24 shrink-0 truncate bg-card text-xs text-muted-foreground',
                    labelClassName,
                )}
                title={label}
            >
                {label}
            </span>

            <div role="list" aria-label={label} className="flex gap-px">
                {children}
            </div>

            {trailing !== undefined && (
                <span className="w-8 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                    {trailing}
                </span>
            )}
        </div>
    );
}
