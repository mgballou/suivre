import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store } from '@/routes/day/meals';
import { classify } from '@/routes/meals';
import type { IsoDate } from '@/types';

export type DayMealEntry = {
    id: number;
    label: string;
    tags: string[];
    matched: boolean;
};

export type DayMeal = {
    id: number;
    type: string;
    time: string;
    entries: DayMealEntry[];
};

export type MealTypeOption = {
    value: string;
    label: string;
};

/** What the classifier suggests for one typed line, before the user rules on it. */
type Suggestion = {
    text: string;
    foodItemId: number | null;
    foodItemName: string | null;
    tags: string[];
    score: number | null;
    matched: boolean;
};

/** A suggestion plus the user's verdict. Rejecting one saves the line as plain text. */
type Draft = Suggestion & { accepted: boolean };

type MealLoggerProps = {
    date: IsoDate;
    meals: DayMeal[];
    mealTypes: MealTypeOption[];
};

/**
 * Meal logging: type what you ate, see what the catalog made of it, correct it,
 * save (D9 — classify, then confirm; never classify silently).
 *
 * A rejected or unmatched line is still saved, as the text the user typed. A
 * miss must never block logging, so the catalog's ignorance is the catalog's
 * problem — it goes to the review queue, and the line simply carries no tags
 * until an operator fixes that.
 */
export function MealLogger({ date, meals, mealTypes }: MealLoggerProps) {
    const [mealType, setMealType] = useState<string | null>(null);
    const [drafts, setDrafts] = useState<Draft[] | null>(null);
    const [saving, setSaving] = useState(false);

    const check = useHttp<{ lines: string[] }, { lines: Suggestion[] }>({
        lines: [],
    });

    const text = check.data.lines.join('\n');
    const typedLines = check.data.lines
        .map((line) => line.trim())
        .filter((line) => line !== '');

    const suggest = async () => {
        const result = await check.post(classify.url());

        setDrafts(
            result.lines.map((line) => ({ ...line, accepted: line.matched })),
        );
    };

    const save = () => {
        if (mealType === null || drafts === null) {
            return;
        }

        setSaving(true);

        router.post(
            store.url({ date }),
            {
                meal_type: mealType,
                entries: drafts.map((draft) => ({
                    text: draft.text,
                    food_item_id: draft.accepted ? draft.foodItemId : null,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    check.reset();
                    setDrafts(null);
                    setMealType(null);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const toggle = (index: number) => {
        setDrafts(
            (current) =>
                current?.map((draft, position) =>
                    position === index
                        ? { ...draft, accepted: !draft.accepted }
                        : draft,
                ) ?? null,
        );
    };

    return (
        <section className="flex flex-col gap-4">
            <div>
                <p className="text-sm text-muted-foreground">
                    One item per line. We will suggest what each is; correct
                    anything we get wrong.
                </p>
            </div>

            {meals.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {meals.map((meal) => (
                        <li
                            key={meal.id}
                            className="flex flex-col gap-2 rounded-md border border-border px-3 py-2 text-sm"
                        >
                            <div className="flex items-center gap-2">
                                <span className="font-medium tabular-nums text-foreground">
                                    {meal.time}
                                </span>
                                <span className="text-foreground">
                                    {meal.type}
                                </span>
                            </div>

                            <ul className="flex flex-col gap-1">
                                {meal.entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex flex-wrap items-center gap-x-2 gap-y-1"
                                    >
                                        <span className="text-foreground">
                                            {entry.label}
                                        </span>

                                        {entry.tags.map((tag) => (
                                            <span
                                                key={tag}
                                                className="rounded-sm bg-accent px-1.5 py-0.5 text-xs text-accent-foreground"
                                            >
                                                {tag}
                                            </span>
                                        ))}

                                        {!entry.matched && (
                                            <span className="text-xs text-muted-foreground">
                                                not in the catalog yet
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            )}

            <fieldset className="flex flex-col gap-2">
                <legend className="sr-only">Meal</legend>
                <div className="flex flex-wrap gap-2">
                    {mealTypes.map((option) => (
                        <label
                            key={option.value}
                            className={cn(
                                'flex min-h-11 cursor-pointer items-center rounded-md border px-3 text-sm font-medium select-none',
                                'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                option.value === mealType
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <input
                                type="radio"
                                name="meal-type"
                                value={option.value}
                                checked={option.value === mealType}
                                onChange={() => setMealType(option.value)}
                                className="sr-only"
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            </fieldset>

            <Textarea
                rows={3}
                value={text}
                onChange={(event) =>
                    check.setData('lines', event.target.value.split('\n'))
                }
                placeholder={'porridge\nblack coffee'}
                aria-label="What you ate, one item per line"
            />

            {drafts === null ? (
                <button
                    type="button"
                    onClick={() => void suggest()}
                    disabled={typedLines.length === 0 || check.processing}
                    className={cn(
                        'min-h-11 rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground',
                        'transition-colors duration-[var(--dur-micro)] ease-quiet',
                        'hover:bg-accent hover:text-accent-foreground',
                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                        'disabled:pointer-events-none disabled:opacity-50',
                    )}
                >
                    {check.processing ? 'Checking…' : 'Check these'}
                </button>
            ) : (
                <div className="flex flex-col gap-3">
                    <ul className="flex flex-col gap-2">
                        {drafts.map((draft, index) => (
                            <li
                                key={`${draft.text}-${index}`}
                                className="rounded-md border border-border px-3 py-2 text-sm"
                            >
                                <div className="flex flex-col gap-1">
                                    <span className="font-medium text-foreground">
                                        {draft.text}
                                    </span>

                                    {draft.matched ? (
                                        <label className="flex cursor-pointer items-start gap-2 select-none">
                                            <input
                                                type="checkbox"
                                                checked={draft.accepted}
                                                onChange={() => toggle(index)}
                                                className="mt-1"
                                            />
                                            <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <span className="text-muted-foreground">
                                                    {draft.foodItemName}
                                                </span>
                                                {draft.tags.map((tag) => (
                                                    <span
                                                        key={tag}
                                                        className="rounded-sm bg-accent px-1.5 py-0.5 text-xs text-accent-foreground"
                                                    >
                                                        {tag}
                                                    </span>
                                                ))}
                                            </span>
                                        </label>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Nothing in the catalog matches this.
                                            It will be saved as you typed it.
                                        </span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={save}
                            disabled={mealType === null || saving}
                            className={cn(
                                'min-h-11 flex-1 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground',
                                'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                                'disabled:pointer-events-none disabled:opacity-50',
                            )}
                        >
                            {mealType === null ? 'Pick a meal first' : 'Save'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setDrafts(null)}
                            className={cn(
                                'min-h-11 rounded-md border border-border bg-background px-3 text-sm font-medium text-muted-foreground',
                                'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                'hover:bg-accent hover:text-accent-foreground',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                            )}
                        >
                            Edit
                        </button>
                    </div>
                </div>
            )}
        </section>
    );
}
