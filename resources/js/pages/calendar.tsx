import { Head, usePage } from '@inertiajs/react';
import { DayCell, type IntensityLevel } from '@/components/suivre/day-cell';

const RAMP_LEVELS: IntensityLevel[] = [0, 1, 2, 3, 4, 5];

export default function Calendar({ month }: { month: string | null }) {
    const today = usePage().props.today;

    return (
        <>
            <Head title="Calendar" />
            <div className="flex flex-1 flex-col gap-8 p-6">
                <header className="space-y-1">
                    <h1 className="text-lg font-semibold tracking-tight">
                        Calendar
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {month ? `Viewing ${month}. ` : ''}The month grid arrives
                        in SUI-6.
                    </p>
                </header>

                <section aria-label="Intensity ramp" className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Intensity ramp
                    </h2>
                    <div className="grid max-w-md grid-cols-6 gap-2">
                        {RAMP_LEVELS.map((level) => (
                            <DayCell
                                key={level}
                                date={`2026-07-0${level + 1}`}
                                level={level}
                                isToday={level === 0}
                                className="aspect-square"
                            />
                        ))}
                    </div>
                    {today && (
                        <p className="text-xs text-muted-foreground tabular-nums">
                            Today (server-derived): {today}
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}
