import {
    INTENSITY_BG,
    INTENSITY_LABELS,
    INTENSITY_LEVELS,
} from '@/lib/intensity';
import { cn } from '@/lib/utils';
import type { IntensityLevel, IsoDate, IsoMonth } from '@/types';

export type HeatmapDay = {
    date: IsoDate;
    level: IntensityLevel;
};

export type CalendarHeatmapProps = {
    month: IsoMonth;
    label: string;
    leadingBlanks: number;
    days: HeatmapDay[];
    className?: string;
};

const DAYS_PER_WEEK = 7;

/** ISO weeks — Monday first, matching the server's `leadingBlanks`. */
const WEEKDAY_INITIALS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

/**
 * A month of intensity on the shared petrol ramp — the dense, read-only
 * sibling of the calendar's `MonthGrid`, and the primitive the timeline view
 * renders. It takes the same server payload the month grid does, so the two
 * cannot disagree about which day sits at which step.
 *
 * Every cell carries a hairline ring and a label naming its step. Step 1 sits
 * at 1.19:1 against the card, so the fill alone is not a legible encoding at
 * the low end; the ring gives every day a boundary and the label carries the
 * value, which is the relief the contrast result obliges.
 *
 * Days outside the month render as spacers, not as a distinct "missing" mark:
 * an absent day is absent, and step 0 already says "not logged".
 */
export function CalendarHeatmap({
    month,
    label,
    leadingBlanks,
    days,
    className,
}: CalendarHeatmapProps) {
    const filled = leadingBlanks + days.length;
    const trailingBlanks = (DAYS_PER_WEEK - (filled % DAYS_PER_WEEK)) % DAYS_PER_WEEK;

    return (
        <figure
            data-month={month}
            className={cn('flex flex-col gap-3', className)}
        >
            <figcaption className="text-sm font-medium tracking-tight tabular-nums">
                {label}
            </figcaption>

            <div
                aria-hidden
                className="grid grid-cols-7 gap-1 text-center text-[0.625rem] font-medium text-muted-foreground"
            >
                {WEEKDAY_INITIALS.map((initial, index) => (
                    <span key={`${initial}-${index}`}>{initial}</span>
                ))}
            </div>

            <div role="list" aria-label={`Daily intensity, ${label}`} className="grid grid-cols-7 gap-1">
                {Array.from({ length: leadingBlanks }, (_, index) => (
                    <span key={`lead-${index}`} aria-hidden data-blank="" />
                ))}

                {days.map((day) => (
                    <span
                        key={day.date}
                        role="listitem"
                        data-date={day.date}
                        data-level={day.level}
                        title={`${day.date} — ${INTENSITY_LABELS[day.level]}`}
                        aria-label={`${day.date}, ${INTENSITY_LABELS[day.level]}`}
                        className={cn(
                            'aspect-square rounded-sm ring-1 ring-inset ring-border',
                            'transition-colors duration-[var(--dur-arrival)] ease-quiet',
                            INTENSITY_BG[day.level],
                        )}
                    />
                ))}

                {Array.from({ length: trailingBlanks }, (_, index) => (
                    <span key={`trail-${index}`} aria-hidden data-blank="" />
                ))}
            </div>

            <IntensityKey />
        </figure>
    );
}

/**
 * The ramp key. Named steps rather than a bare "less → more" gradient, because
 * the two lightest steps are near-indistinguishable from the surface and from
 * each other at cell size.
 */
function IntensityKey() {
    return (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span>{INTENSITY_LABELS[0]}</span>
            <span className="flex items-center gap-0.5">
                {INTENSITY_LEVELS.map((level) => (
                    <span
                        key={level}
                        title={INTENSITY_LABELS[level]}
                        className={cn(
                            'size-3 rounded-xs ring-1 ring-inset ring-border',
                            INTENSITY_BG[level],
                        )}
                    />
                ))}
            </span>
            <span>{INTENSITY_LABELS[5]}</span>
        </div>
    );
}
