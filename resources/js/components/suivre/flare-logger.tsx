import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { ScaleOption } from '@/components/suivre/scale-picker';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { flare } from '@/routes/day/conditions';
import type { ConditionHue, IsoDate } from '@/types';

export type DayFlare = {
    id: number;
    conditionName: string;
    hue: ConditionHue;
    intensity: string;
    time: string;
    duration: string | null;
    note: string | null;
};

type FlareTarget = {
    id: number;
    name: string;
};

type FlareLoggerProps = {
    date: IsoDate;
    conditions: FlareTarget[];
    intensities: ScaleOption[];
    flares: DayFlare[];
};

/**
 * Acute flare entry. A flare is logged mid-flare, so the fast path is two taps
 * — condition, then intensity — and the intensity tap is what writes. Duration
 * and a note sit behind a disclosure because they are what a user adds
 * afterwards, if at all.
 *
 * Intensity is never encoded in colour here. D20 rules out red, and a
 * three-step severity scale rendered as a traffic light is exactly the reading
 * the design system refuses.
 */
export function FlareLogger({
    date,
    conditions,
    intensities,
    flares,
}: FlareLoggerProps) {
    const [target, setTarget] = useState<number | null>(
        conditions[0]?.id ?? null,
    );
    const [duration, setDuration] = useState('');
    const [note, setNote] = useState('');
    const durationField = useId();
    const noteField = useId();

    const log = (intensity: number) => {
        if (target === null) {
            return;
        }

        router.post(
            flare.url({ date, condition: target }),
            {
                intensity,
                duration_minutes: duration === '' ? null : Number(duration),
                note: note === '' ? null : note,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDuration('');
                    setNote('');
                },
            },
        );
    };

    if (conditions.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4">
            <div>
                <h2 className="text-sm font-medium text-foreground">Flare</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Log an episode as it happens. Two taps is the whole thing.
                </p>
            </div>

            {conditions.length > 1 && (
                <fieldset className="flex flex-col gap-2">
                    <legend className="sr-only">Condition</legend>
                    <div className="flex flex-wrap gap-2">
                        {conditions.map((condition) => (
                            <label
                                key={condition.id}
                                className={cn(
                                    'flex min-h-11 cursor-pointer items-center rounded-md border px-3 text-sm font-medium select-none',
                                    'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                    'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                    condition.id === target
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="flare-condition"
                                    value={condition.id}
                                    checked={condition.id === target}
                                    onChange={() => setTarget(condition.id)}
                                    className="sr-only"
                                />
                                {condition.name}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            <div className="flex gap-2">
                {intensities.map((intensity) => (
                    <button
                        key={intensity.value}
                        type="button"
                        onClick={() => log(intensity.value)}
                        className={cn(
                            'min-h-11 flex-1 rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground',
                            'transition-colors duration-[var(--dur-micro)] ease-quiet',
                            'hover:bg-accent hover:text-accent-foreground',
                            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                        )}
                    >
                        {intensity.label}
                    </button>
                ))}
            </div>

            <Collapsible>
                <CollapsibleTrigger className="group inline-flex min-h-11 items-center gap-1 text-sm text-muted-foreground transition-colors duration-[var(--dur-micro)] ease-quiet hover:text-foreground">
                    Add detail
                    <ChevronDown
                        aria-hidden
                        className="size-4 transition-transform duration-[var(--dur-base)] ease-quiet group-data-[state=open]:rotate-180"
                    />
                </CollapsibleTrigger>

                <CollapsibleContent className="flex flex-col gap-4 pt-3">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor={durationField}>Duration (minutes)</Label>
                        <Input
                            id={durationField}
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={1440}
                            value={duration}
                            onChange={(event) =>
                                setDuration(event.target.value)
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor={noteField}>Note</Label>
                        <Textarea
                            id={noteField}
                            rows={2}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder="What it felt like, what you had tried."
                        />
                    </div>
                </CollapsibleContent>
            </Collapsible>

            {flares.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {flares.map((entry) => (
                        <li
                            key={entry.id}
                            data-hue={entry.hue}
                            className="flex flex-col gap-1 rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <div className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className="size-2.5 shrink-0 rounded-full bg-condition-5"
                                />
                                <span className="font-medium tabular-nums text-foreground">
                                    {entry.time}
                                </span>
                                <span className="text-foreground">
                                    {entry.conditionName}
                                </span>
                                <span className="text-muted-foreground">
                                    {entry.intensity}
                                    {entry.duration && ` · ${entry.duration}`}
                                </span>
                            </div>
                            {entry.note && (
                                <p className="text-muted-foreground">
                                    {entry.note}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
