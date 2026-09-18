import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { DayCell, type IntensityLevel } from '@/components/suivre/day-cell';
import { cn } from '@/lib/utils';
import { calendar, day } from '@/routes';
import type { IsoDate, IsoMonth } from '@/types';

export type CalendarDay = {
    date: IsoDate;
    level: IntensityLevel;
    hasCheckin: boolean;
    isToday: boolean;
    isReachable: boolean;
};

/** A neighbour is null when the journal does not reach it. */
export type MonthGridProps = {
    month: IsoMonth;
    label: string;
    previousMonth: IsoMonth | null;
    nextMonth: IsoMonth | null;
    leadingBlanks: number;
    days: CalendarDay[];
};

/**
 * ISO weeks — Monday first. The server computes `leadingBlanks` from the same
 * convention, so the two must change together.
 */
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const NAV_CLASSES =
    'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-muted-foreground transition-colors duration-[var(--dur-micro)] ease-quiet hover:bg-accent hover:text-foreground';

/** Holds the nav control's place so dropping one does not re-centre the title. */
const NAV_SPACER = 'inline-flex min-h-11 min-w-11';

/**
 * The month calendar. Navigation pans in the direction of time (D20): a later
 * month enters from the right, an earlier one from the left. The direction is
 * read from the previous render because Inertia re-renders this component in
 * place rather than remounting it.
 *
 * Navigation stops where the journal does. A month or a day the server has
 * bounded arrives as a null neighbour or an unreachable cell, and neither is
 * rendered as a link — the interface refuses the trip rather than letting a
 * user find out by making the write.
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
                {previousMonth === null ? (
                    <span aria-hidden className={NAV_SPACER} />
                ) : (
                    <Link
                        href={calendar(previousMonth)}
                        aria-label="Previous month"
                        className={NAV_CLASSES}
                    >
                        <ChevronLeft className="size-5" aria-hidden />
                    </Link>
                )}

                <h1
                    aria-live="polite"
                    className="text-base font-semibold tracking-tight tabular-nums"
                >
                    {label}
                </h1>

                {nextMonth === null ? (
                    <span aria-hidden className={NAV_SPACER} />
                ) : (
                    <Link
                        href={calendar(nextMonth)}
                        aria-label="Next month"
                        className={NAV_CLASSES}
                    >
                        <ChevronRight className="size-5" aria-hidden />
                    </Link>
                )}
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

                {days.map((calendarDay) => {
                    const cell = (
                        <DayCell
                            date={calendarDay.date}
                            level={calendarDay.level}
                            isToday={calendarDay.isToday}
                            hasCheckin={calendarDay.hasCheckin}
                            className={cn(
                                'aspect-square w-full',
                                !calendarDay.isReachable && 'opacity-40',
                            )}
                        />
                    );

                    return calendarDay.isReachable ? (
                        <Link
                            key={calendarDay.date}
                            href={day(calendarDay.date)}
                            className="block rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            {cell}
                        </Link>
                    ) : (
                        <span key={calendarDay.date} className="block">
                            {cell}
                        </span>
                    );
                })}
            </div>
        </section>
    );
}
