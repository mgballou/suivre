import { cn } from '@/lib/utils';

/**
 * One tap target, projected from a domain enum server-side (BuildDayView), so
 * the scale's cases and their order live in PHP and are never restated here.
 */
export type ScaleOption = {
    value: number;
    label: string;
};

type ScalePickerProps = {
    name: string;
    label: string;
    options: ScaleOption[];
    value: number | null;
    onSelect: (value: number) => void;
};

/**
 * A single-tap scale for one check-in signal.
 *
 * Native radios rather than buttons with `role="radio"`: arrow-key navigation,
 * grouping and announcement come from the platform instead of being
 * half-reimplemented. The input is visually hidden and the label carries the
 * 44px hit area the design system requires of every tap control.
 *
 * Selecting is one-way — re-tapping the chosen option does not clear it. A
 * mis-tap is corrected by tapping the right option, which costs the same one
 * tap, and a stray double-tap would otherwise silently discard a value.
 */
export function ScalePicker({
    name,
    label,
    options,
    value,
    onSelect,
}: ScalePickerProps) {
    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium text-foreground">
                {label}
            </legend>

            <div className="flex gap-2">
                {options.map((option) => {
                    const selected = option.value === value;

                    return (
                        <label
                            key={option.value}
                            data-testid={`${name}-${option.value}`}
                            className={cn(
                                'flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-md border px-3 py-2 text-center text-sm font-medium select-none',
                                'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                selected
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={selected}
                                onChange={() => onSelect(option.value)}
                                className="sr-only"
                            />
                            {option.label}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
