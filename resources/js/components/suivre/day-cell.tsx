import { cn } from '@/lib/utils';
import type { IsoDate } from '@/types';

/** Step 0 is an unlogged day; 1–5 climb the ramp. */
export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;

type DayCellProps = {
    date: IsoDate;
    level: IntensityLevel;
    isToday?: boolean;
    hasCheckin?: boolean;
    className?: string;
};

/** Listed literally rather than interpolated, so Tailwind's JIT emits them. */
const RAMP_BG: Record<IntensityLevel, string> = {
    0: 'bg-intensity-0',
    1: 'bg-intensity-1',
    2: 'bg-intensity-2',
    3: 'bg-intensity-3',
    4: 'bg-intensity-4',
    5: 'bg-intensity-5',
};

/**
 * One calendar cell. The day number sits in a neutral chip so its contrast is
 * decoupled from the swatch and stays AA-legible at every step, and a logged day
 * carries a marker dot because the ramp's lower steps are deliberately quiet.
 * The background eases over `--dur-arrival` so a logged colour arrives rather
 * than snapping (D20) — colour-only, so reduced-motion needs no special case.
 */
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
