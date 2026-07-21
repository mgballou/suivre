import type { IntensityLevel } from '@/types';

/**
 * The petrol intensity ramp (D20) — the single definition of the scale every
 * surface that encodes condition intensity draws from. The calendar's day cell
 * and the heatmap read it from here so a second, drifting ramp cannot appear.
 *
 * It is a *sequential* scale: one hue encoding magnitude, deepening in light
 * mode and lightening in dark. Step 0 is an unlogged day and is allowed to
 * recede toward the surface — an empty cell is quiet, not accusatory.
 */
export const INTENSITY_LEVELS: readonly IntensityLevel[] = [0, 1, 2, 3, 4, 5];

/** Listed literally rather than interpolated, so Tailwind's JIT emits them. */
export const INTENSITY_BG: Record<IntensityLevel, string> = {
    0: 'bg-intensity-0',
    1: 'bg-intensity-1',
    2: 'bg-intensity-2',
    3: 'bg-intensity-3',
    4: 'bg-intensity-4',
    5: 'bg-intensity-5',
};

/**
 * Read aloud by screen readers and shown in the heatmap tooltip. Step 1 sits at
 * 1.19:1 against the card surface, so fill alone never identifies a level —
 * these labels are the relief channel the contrast result obliges.
 */
export const INTENSITY_LABELS: Record<IntensityLevel, string> = {
    0: 'No entry',
    1: 'Barely there',
    2: 'Mild',
    3: 'Moderate',
    4: 'Strong',
    5: 'Severe',
};
