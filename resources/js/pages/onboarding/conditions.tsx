import { Head, useForm } from '@inertiajs/react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CONDITION_HUES } from '@/lib/condition-hue';
import { cn } from '@/lib/utils';
import { store as startTracking } from '@/routes/onboarding/conditions';
import type { ConditionHue } from '@/types';

type Choice = {
    name: string;
    color: ConditionHue;
};

type OnboardingConditionsProps = {
    suggestions: { name: string; hue: ConditionHue }[];
};

function nextHue(chosen: Choice[]): ConditionHue {
    const taken = new Set(chosen.map((choice) => choice.color));

    return CONDITION_HUES.find((hue) => !taken.has(hue)) ?? CONDITION_HUES[0];
}

/**
 * First run. The journal records symptoms against a name, so there is nothing
 * to show on a calendar until the user has said what they are watching — this
 * screen is the gate, not a welcome tour.
 *
 * Copy stays descriptive: no congratulation for finishing it, and no count of
 * how many conditions a "good" answer has.
 */
export default function OnboardingConditions({
    suggestions,
}: OnboardingConditionsProps) {
    const [custom, setCustom] = useState('');
    const customField = useId();

    const form = useForm<{ conditions: Choice[] }>({ conditions: [] });

    const chosen = form.data.conditions;

    const isChosen = (name: string) =>
        chosen.some((choice) => choice.name === name);

    const toggle = (name: string, color: ConditionHue) => {
        form.setData(
            'conditions',
            isChosen(name)
                ? chosen.filter((choice) => choice.name !== name)
                : [...chosen, { name, color }],
        );
    };

    const addCustom = () => {
        const name = custom.trim();

        if (name === '' || isChosen(name)) {
            return;
        }

        form.setData('conditions', [
            ...chosen,
            { name, color: nextHue(chosen) },
        ]);
        setCustom('');
    };

    const options: Choice[] = [
        ...suggestions.map((suggestion) => ({
            name: suggestion.name,
            color: suggestion.hue,
        })),
        ...chosen.filter(
            (choice) =>
                !suggestions.some(
                    (suggestion) => suggestion.name === choice.name,
                ),
        ),
    ];

    return (
        <>
            <Head title="What do you track?" />

            <div className="flex min-h-svh flex-col items-center justify-center bg-background p-6">
                <div className="flex w-full max-w-lg flex-col gap-8">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-lg font-semibold tracking-tight">
                            What do you track?
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pick the conditions you want to rate each day, or
                            name your own. You can change any of this later.
                        </p>
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(startTracking.url());
                        }}
                        className="flex flex-col gap-6"
                    >
                        <fieldset className="flex flex-col gap-3">
                            <legend className="mb-2 text-sm font-medium text-foreground">
                                Conditions
                            </legend>

                            <div className="flex flex-wrap gap-2">
                                {options.map((option) => (
                                    <label
                                        key={option.name}
                                        data-hue={option.color}
                                        data-testid={`suggestion-${option.name}`}
                                        className={cn(
                                            'flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm font-medium select-none',
                                            'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                            'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                            isChosen(option.name)
                                                ? 'border-foreground text-foreground'
                                                : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                        )}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={isChosen(option.name)}
                                            onChange={() =>
                                                toggle(
                                                    option.name,
                                                    option.color,
                                                )
                                            }
                                            className="sr-only"
                                        />
                                        <span
                                            aria-hidden
                                            className="size-2.5 shrink-0 rounded-full bg-condition-5"
                                        />
                                        {option.name}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor={customField}>
                                Something else
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id={customField}
                                    value={custom}
                                    maxLength={60}
                                    placeholder="Jaw tension"
                                    onChange={(event) =>
                                        setCustom(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            addCustom();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addCustom}
                                >
                                    Add
                                </Button>
                            </div>
                        </div>

                        <InputError message={form.errors.conditions} />

                        <Button
                            type="submit"
                            disabled={chosen.length === 0 || form.processing}
                        >
                            Start tracking
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
