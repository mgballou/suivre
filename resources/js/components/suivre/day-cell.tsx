import { cn } from '@/lib/utils';

export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;

type DayCellProps = {
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    /** 0 = no entry; 1–5 climb the ramp. */
    level: IntensityLevel;
    isToday?: boolean;
    className?: string;
};

/**
 * One calendar cell. The ramp fills the cell; the day number lives in a neutral
 * bordered chip so it stays AA-legible at every intensity — its contrast is
 * decoupled from the swatch. SUI-6 assembles these into the month grid.
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

export function DayCell({ date, level, isToday = false, className }: DayCellProps) {
    const dayNumber = Number(date.slice(8, 10));

    return (
        <div
            data-level={level}
            data-today={isToday ? '' : undefined}
            aria-label={`${date}, intensity ${level} of 5`}
            className={cn(
                'relative flex min-h-11 min-w-11 rounded-md',
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
        </div>
    );
}
