import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { IntensityPicker } from '@/components/suivre/intensity-picker';
import { index as conditionsIndex } from '@/routes/conditions';
import { clear as clearRating, rate } from '@/routes/day/conditions';
import type {
    ConditionHue,
    ConditionIntensity,
    IntensityLevel,
    IsoDate,
} from '@/types';

export type DayCondition = {
    id: number;
    name: string;
    hue: ConditionHue;
    intensity: ConditionIntensity | null;
    level: IntensityLevel;
};

type ConditionRatingsProps = {
    date: IsoDate;
    conditions: DayCondition[];
};

/**
 * The day's condition ratings. Each picker writes on tap — there is no save
 * button between the gesture and the record, as with the check-in — and clears
 * on its own control, because zero is a rating rather than a gap.
 *
 * The draft is local state seeded from the server's saved ratings so the
 * selection is immediate while the round-trip is in flight; a rejected write
 * drops back out of it, so the picker never shows a value the server refused.
 * The caller must key this component by date: Inertia re-renders the day page
 * in place when moving between days, and stale drafts would otherwise carry
 * over.
 *
 * Out-of-range dates are the one write this surface is expected to lose — the
 * journal is bounded to days the user has lived through — so the reason is
 * shown rather than swallowed.
 */
export function ConditionRatings({ date, conditions }: ConditionRatingsProps) {
    const [draft, setDraft] = useState<
        Record<number, ConditionIntensity | null>
    >({});
    const [error, setError] = useState<string>();

    const forget = (condition: DayCondition) =>
        setDraft((current) => {
            const next = { ...current };
            delete next[condition.id];

            return next;
        });

    const save = (condition: DayCondition, intensity: ConditionIntensity) => {
        setDraft((current) => ({ ...current, [condition.id]: intensity }));
        setError(undefined);

        router.post(
            rate.url({ date, condition: condition.id }),
            { intensity },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    forget(condition);
                    setError(errors.date ?? errors.intensity);
                },
            },
        );
    };

    const clear = (condition: DayCondition) => {
        setDraft((current) => ({ ...current, [condition.id]: null }));
        setError(undefined);

        router.delete(clearRating.url({ date, condition: condition.id }), {
            preserveScroll: true,
            preserveState: true,
            onError: () => forget(condition),
        });
    };

    if (conditions.length === 0) {
        return (
            <section className="flex flex-col gap-2">
                <h2 className="text-sm font-medium text-foreground">
                    Conditions
                </h2>
                <p className="text-sm text-muted-foreground">
                    You are not tracking any conditions right now.{' '}
                    <Link
                        href={conditionsIndex()}
                        className="underline underline-offset-4 hover:text-foreground"
                    >
                        Manage conditions
                    </Link>
                    .
                </p>
            </section>
        );
    }

    return (
        <section className="flex flex-col gap-8">
            <h2 className="text-sm font-medium text-foreground">Conditions</h2>

            <InputError message={error} />

            {conditions.map((condition) => (
                <IntensityPicker
                    key={condition.id}
                    name={`condition-${condition.id}`}
                    label={condition.name}
                    hue={condition.hue}
                    value={
                        condition.id in draft
                            ? draft[condition.id]
                            : condition.intensity
                    }
                    level={condition.level}
                    onSelect={(intensity) => save(condition, intensity)}
                    onClear={() => clear(condition)}
                />
            ))}
        </section>
    );
}
