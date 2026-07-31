import type { ConditionHue, IntensityLevel } from '@/types';

/**
 * A condition's ramp is not a second scale beside petrol's — it is the same
 * six-step shape in another hue, and which hue is decided by the `data-hue`
 * attribute these classes resolve their custom properties through. That is why
 * one Record covers all seven hues.
 *
 * Listed literally rather than interpolated, so Tailwind's JIT emits them.
 */
export const CONDITION_BG: Record<IntensityLevel, string> = {
    0: 'bg-condition-0',
    1: 'bg-condition-1',
    2: 'bg-condition-2',
    3: 'bg-condition-3',
    4: 'bg-condition-4',
    5: 'bg-condition-5',
};

/**
 * The foreground a ramp step can legibly carry. Every ramp — petrol and all
 * seven condition hues — is built to one luminance profile per step, so the ink
 * belongs to the step and not to the hue. Kept in step with PHP's
 * `RampStep::ink()`, which `ConditionHueTest` proves clears WCAG AA.
 */
export const RAMP_INK: Record<IntensityLevel, string> = {
    0: 'text-ramp-ink-0',
    1: 'text-ramp-ink-1',
    2: 'text-ramp-ink-2',
    3: 'text-ramp-ink-3',
    4: 'text-ramp-ink-4',
    5: 'text-ramp-ink-5',
};

export const CONDITION_HUES: readonly ConditionHue[] = [
    'clay',
    'ochre',
    'moss',
    'marine',
    'indigo',
    'violet',
    'plum',
];
