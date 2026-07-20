import { cn } from '@/lib/utils';

export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;

type DayCellProps = {
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    /** 0 = no entry; 1–5 climb the ramp. */
    level: IntensityLevel;
    isToday?: boolean;
    /** A `DailyCheckin` exists for this date. */
    hasCheckin?: boolean;
    className?: string;
};

/**
 * One calendar cell. The ramp fills the cell; the day number lives in a neutral
 * bordered chip so it stays AA-legible at every intensity — its contrast is
 * decoupled from the swatch. `MonthGrid` assembles these into the month.
 *
 * A logged day also carries a marker dot: the ramp's lower steps are
 * deliberately quiet, and presence of an entry must read at a glance.
 *
 * The background eases over `--dur-arrival` — a logged colour arrives rather
 * than snapping (D20). Colour-only, so reduced-motion needs no special case.
 *
 * Ramp classes are listed literally so Tailwind's JIT emits them.
 */
const RAMP_BG: Record<IntensityLevel, string> = {
    0: 'bg-intensity-0',
    1: 'bg-intensity-1',
    2: 'bg-intensity-2',
    3: 'bg-intensity-3',
    4: 'bg-intensity-4',
    5: 'bg-intensity-5',
};

export function DayCell({
    date,
    level,
    isToday = false,
    hasCheckin = false,
    className,
}: DayCellProps) {
    const dayNumber = Number(date.slice(8, 10));

    return (
        <div
            data-level={level}
            data-today={isToday ? '' : undefined}
            data-checkin={hasCheckin ? '' : undefined}
            aria-label={`${date}, intensity ${level} of 5${hasCheckin ? ', checked in' : ''}`}
            className={cn(
                'relative flex min-h-11 min-w-11 rounded-md',
                'transition-colors duration-[var(--dur-arrival)] ease-quiet',
                RAMP_BG[level],
                isToday && 'ring-2 ring-primary ring-inset',
                className,
            )}
        >
            <span
                className={cn(
                    'absolute left-1 top-1 inline-flex min-w-5 items-center justify-center',
                    'rounded-sm border border-border bg-background px-1 py-0.5',
                    'text-xs font-medium tabular-nums text-foreground',
                )}
            >
                {dayNumber}
            </span>

            {hasCheckin && (
                <span
                    aria-hidden
                    className="absolute inset-x-0 bottom-1.5 mx-auto size-1.5 rounded-full bg-primary"
                />
            )}
        </div>
    );
}
