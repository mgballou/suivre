import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import {
    CheckinForm,
    type CheckinScales,
    type CheckinValues,
} from '@/components/suivre/checkin-form';
import { RAMP_BG, type IntensityLevel } from '@/components/suivre/day-cell';
import { cn } from '@/lib/utils';
import { calendar } from '@/routes';
import type { IsoDate, IsoMonth } from '@/types';

type DayProps = {
    date: IsoDate;
    label: string;
    month: IsoMonth;
    level: IntensityLevel;
    isToday: boolean;
    checkin: CheckinValues;
    scales: CheckinScales;
};

/**
 * One day's check-in. The view lifts out of the tapped calendar cell on entry
 * and lowers back on the way home (D20); the exit plays while Inertia's request
 * is already in flight, so the motion costs nothing in perceived latency.
 */
export default function Day({
    date,
    label,
    month,
    level,
    isToday,
    checkin,
    scales,
}: DayProps) {
    const [leaving, setLeaving] = useState(false);

    return (
        <>
            <Head title={label} />

            <div
                className={cn(
                    'flex flex-1 flex-col p-4 sm:p-6',
                    leaving ? 'lower-out' : 'lift-in',
                )}
            >
                <div className="mx-auto w-full max-w-2xl">
                    <header className="mb-8 flex items-center gap-2">
                        <Link
                            href={calendar(month)}
                            onClick={() => setLeaving(true)}
                            aria-label="Back to calendar"
                            className="-ml-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-muted-foreground transition-colors duration-[var(--dur-micro)] ease-quiet hover:bg-accent hover:text-foreground"
                        >
                            <ChevronLeft className="size-5" aria-hidden />
                        </Link>

                        <div className="flex flex-1 flex-col">
                            <h1 className="text-base font-semibold tracking-tight tabular-nums">
                                {label}
                            </h1>
                            {isToday && (
                                <p className="text-xs text-muted-foreground">
                                    Today
                                </p>
                            )}
                        </div>

                        {/*
                         * The whole acknowledgement (D20): once the day is
                         * recorded its colour eases in over --dur-arrival
                         * instead of snapping. Colour-only, so reduced motion
                         * needs no special case — nothing travels.
                         */}
                        <span
                            aria-hidden
                            data-level={level}
                            className={cn(
                                'size-8 rounded-md',
                                'transition-colors duration-[var(--dur-arrival)] ease-quiet',
                                RAMP_BG[level],
                            )}
                        />
                    </header>

                    <CheckinForm
                        key={date}
                        date={date}
                        values={checkin}
                        scales={scales}
                    />
                </div>
            </div>
        </>
    );
}
