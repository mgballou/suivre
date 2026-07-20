import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { DayCell, type IntensityLevel } from '@/components/suivre/day-cell';
import { cn } from '@/lib/utils';
import { calendar, day } from '@/routes';

export type CalendarDay = {
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    level: IntensityLevel;
    hasCheckin: boolean;
    isToday: boolean;
};

export type MonthGridProps = {
    /** `YYYY-MM`. */
    month: string;
    /** Rendered month heading, e.g. `July 2026`. */
    label: string;
    previousMonth: string;
    nextMonth: string;
    /** Empty cells before the 1st, so the grid lines up under Monday. */
    leadingBlanks: number;
    days: CalendarDay[];
};

/** ISO weeks — Monday first, matching the server's `leadingBlanks`. */
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const NAV_CLASSES =
    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-muted-foreground transition-colors duration-[var(--dur-micro)] ease-quiet hover:bg-accent hover:text-foreground';

/**
 * The month calendar. Every date comes from the server — the client never
 * derives a day, because `new Date()` reads the device timezone rather than
 * the user's configured one.
 *
 * Navigation pans in the direction of time (D20): a later month enters from
 * the right, an earlier month from the left. Direction is read from the
 * previous render, since Inertia re-renders this component in place rather
 * than remounting it.
 */
export function MonthGrid({
    month,
    label,
    previousMonth,
    nextMonth,
    leadingBlanks,
    days,
}: MonthGridProps) {
    const rendered = useRef(month);
    const direction =
        month === rendered.current
            ? 'none'
            : month > rendered.current
              ? 'forward'
              : 'back';

    useEffect(() => {
        rendered.current = month;
    }, [month]);

    return (
        <section aria-label="Month calendar" className="mx-auto w-full max-w-2xl">
            <header className="mb-4 flex items-center justify-between gap-2">
                <Link
                    href={calendar(previousMonth)}
                    aria-label="Previous month"
                    className={NAV_CLASSES}
                >
                    <ChevronLeft className="size-5" aria-hidden />
                </Link>

                <h1
                    aria-live="polite"
                    className="text-base font-semibold tracking-tight tabular-nums"
                >
                    {label}
                </h1>

                <Link
                    href={calendar(nextMonth)}
                    aria-label="Next month"
                    className={NAV_CLASSES}
                >
                    <ChevronRight className="size-5" aria-hidden />
                </Link>
            </header>

            <div
                aria-hidden
                className="mb-2 grid grid-cols-7 gap-1.5 text-center text-xs font-medium text-muted-foreground sm:gap-2"
            >
                {WEEKDAYS.map((weekday) => (
                    <span key={weekday}>{weekday}</span>
                ))}
            </div>

            <div
                key={month}
                data-direction={direction}
                className={cn(
                    'grid grid-cols-7 gap-1.5 sm:gap-2',
                    direction === 'forward' && 'pan-forward',
                    direction === 'back' && 'pan-back',
                )}
            >
                {Array.from({ length: leadingBlanks }, (_, index) => (
                    <span key={`blank-${index}`} aria-hidden />
                ))}

                {days.map((calendarDay) => (
                    <Link
                        key={calendarDay.date}
                        href={day(calendarDay.date)}
                        className="block rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                    >
                        <DayCell
                            date={calendarDay.date}
                            level={calendarDay.level}
                            isToday={calendarDay.isToday}
                            hasCheckin={calendarDay.hasCheckin}
                            className="aspect-square w-full"
                        />
                    </Link>
                ))}
            </div>
        </section>
    );
}
