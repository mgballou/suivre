import { cn } from '@/lib/utils';
import type { ConditionHue } from '@/types';

export type HueOption = {
    value: ConditionHue;
    label: string;
    group: 'warm' | 'cool';
};

type HuePickerProps = {
    name: string;
    options: HueOption[];
    value: ConditionHue;
    onSelect: (hue: ConditionHue) => void;
};

const GROUPS: { key: HueOption['group']; label: string }[] = [
    { key: 'warm', label: 'Warm' },
    { key: 'cool', label: 'Cool' },
];

/**
 * Colour is chosen from the curated set, never picked freely (D20): a
 * free-picked hue has no ramp, and no ramp means no dark mode and no contrast
 * guarantee. The options are projected from the PHP enum, so the set cannot
 * drift between the two halves of the app.
 */
export function HuePicker({ name, options, value, onSelect }: HuePickerProps) {
    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-2 text-sm font-medium text-foreground">
                Colour
            </legend>

            {GROUPS.map((group) => (
                <div key={group.key} className="flex items-center gap-2">
                    <span className="w-10 shrink-0 text-xs text-muted-foreground">
                        {group.label}
                    </span>

                    <div className="flex flex-wrap gap-2">
                        {options
                            .filter((option) => option.group === group.key)
                            .map((option) => (
                                <label
                                    key={option.value}
                                    data-hue={option.value}
                                    data-testid={`${name}-${option.value}`}
                                    className={cn(
                                        'flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-md border select-none',
                                        'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                        'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                        option.value === value
                                            ? 'border-foreground'
                                            : 'border-border',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={name}
                                        value={option.value}
                                        checked={option.value === value}
                                        onChange={() => onSelect(option.value)}
                                        className="sr-only"
                                    />
                                    <span
                                        aria-hidden
                                        className="size-6 rounded-full bg-condition-5"
                                    />
                                    <span className="sr-only">
                                        {option.label}
                                    </span>
                                </label>
                            ))}
                    </div>
                </div>
            ))}
        </fieldset>
    );
}
