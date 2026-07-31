import { CONDITION_BG, RAMP_INK } from '@/lib/condition-hue';
import { cn } from '@/lib/utils';
import type { ConditionHue, ConditionIntensity, IntensityLevel } from '@/types';

type IntensityPickerProps = {
    name: string;
    label: string;
    hue: ConditionHue;
    value: ConditionIntensity | null;
    level: IntensityLevel;
    onSelect: (value: ConditionIntensity) => void;
};

const SCALE: readonly ConditionIntensity[] = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

/**
 * One condition's 0–10 rating for a day.
 *
 * Native radios rather than buttons with `role="radio"`, matching ScalePicker:
 * arrow-key navigation, grouping and announcement come from the platform. The
 * input is visually hidden and the label carries the 44px hit area — hence six
 * columns on a phone, where eleven 44px targets do not fit a row, and eleven
 * from `sm` up.
 *
 * `level` is the ramp step of the *saved* rating and comes from the server,
 * while `value` is the local draft. Only the saved step is coloured, so a tap
 * rings the option immediately and its colour arrives on the round trip (D20)
 * rather than the client re-deriving a bucket that RampStep already owns.
 *
 * Selecting is one-way, as on every other scale here: re-tapping the chosen
 * value does not clear it. Zero is a legitimate rating — "nothing today" — so
 * clearing would need a separate gesture, not an accidental double tap.
 */
export function IntensityPicker({
    name,
    label,
    hue,
    value,
    level,
    onSelect,
}: IntensityPickerProps) {
    return (
        <fieldset data-hue={hue} className="flex flex-col gap-2">
            <legend className="mb-2 flex items-center gap-2 text-sm font-medium text-foreground">
                <span
                    aria-hidden
                    className="size-2.5 shrink-0 rounded-full bg-condition-5"
                />
                {label}
            </legend>

            <div className="grid grid-cols-6 gap-1 sm:grid-cols-11">
                {SCALE.map((step) => {
                    const selected = step === value;

                    return (
                        <label
                            key={step}
                            data-testid={`${name}-${step}`}
                            className={cn(
                                'flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-md border text-sm font-medium tabular-nums select-none',
                                'transition-colors duration-[var(--dur-arrival)] ease-quiet',
                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-background',
                                selected
                                    ? cn(
                                          'border-transparent ring-2 ring-ring',
                                          CONDITION_BG[level],
                                          RAMP_INK[level],
                                      )
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={step}
                                checked={selected}
                                onChange={() => onSelect(step)}
                                className="sr-only"
                            />
                            {step}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
