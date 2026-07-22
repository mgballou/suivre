import type { ChartConfig } from '@/components/ui/chart';

export type ChartSeries = {
    key: string;
    label: string;
};

/**
 * What the colour is doing, which is the only thing a call site gets to choose.
 *
 * `magnitude` is one measure over time and wears petrol — the app's own hue,
 * the same one the calendar ramp climbs, so a chart and the month grid read as
 * one instrument. `identity` is "which series", and wears the categorical
 * slots, which petrol cannot do: at OKLCH chroma 0.064 it is under the 0.10
 * floor and reads as gray.
 *
 * Modelling the two as a union rather than a string plus a `series[]` makes the
 * single-series rule for magnitude unrepresentable rather than merely documented.
 */
export type ChartIntent =
    | { kind: 'magnitude'; series: ChartSeries }
    | { kind: 'identity'; series: ChartSeries[] };

/**
 * Assigned in fixed order and never cycled — the order is the CVD-safety
 * mechanism, not a preference. All four clear the lightness band, the chroma
 * floor and the all-pairs CVD separation in both modes against Suivre's card
 * surfaces. A fifth series folds into "Other", facets, or is labelled directly;
 * it never becomes a generated hue.
 */
const SERIES_SLOTS = [
    'var(--color-series-1)',
    'var(--color-series-2)',
    'var(--color-series-3)',
    'var(--color-series-4)',
] as const;

export const MAX_IDENTITY_SERIES = SERIES_SLOTS.length;

const MAGNITUDE_COLOR = 'var(--color-primary)';

export function seriesOf(intent: ChartIntent): ChartSeries[] {
    return intent.kind === 'magnitude' ? [intent.series] : intent.series;
}

/**
 * Build the shadcn `ChartConfig` from intent. Colour is resolved here and
 * nowhere else, so a chart is declared with data and intent rather than
 * re-styled at each call site.
 */
export function chartConfig(intent: ChartIntent): ChartConfig {
    const series = seriesOf(intent);

    if (series.length > MAX_IDENTITY_SERIES) {
        throw new Error(
            `A chart carries at most ${MAX_IDENTITY_SERIES} series; got ${series.length}. Fold the rest into "Other" or facet.`,
        );
    }

    return Object.fromEntries(
        series.map((entry, index) => [
            entry.key,
            {
                label: entry.label,
                color:
                    intent.kind === 'magnitude'
                        ? MAGNITUDE_COLOR
                        : SERIES_SLOTS[index],
            },
        ]),
    );
}

/**
 * Mark and chrome specs, fixed across every chart. Grid and axis are hairline
 * and recessive (1.24:1 against the surface — present, never competing); axis
 * ticks are tabular so columns of figures align.
 */
export const CHART_GRID = {
    stroke: 'var(--color-border)',
    strokeWidth: 1,
    vertical: false,
} as const;

export const CHART_AXIS = {
    tickLine: false,
    axisLine: false,
    tickMargin: 8,
    stroke: 'var(--color-muted-foreground)',
    className: 'tabular-nums',
} as const;

/** 2px, round join and cap; the surface-coloured ring keeps the active dot legible where it crosses the line. */
export const CHART_LINE = {
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    dot: false,
    activeDot: { r: 4, strokeWidth: 2, stroke: 'var(--color-card)' },
} as const;

/** A wash, never a saturated block. */
export const CHART_AREA_OPACITY = 0.1;

/** 4px rounded data-end, square at the baseline; the band's leftover stays air. */
export const CHART_BAR = {
    radius: [4, 4, 0, 0] as [number, number, number, number],
    maxBarSize: 24,
};
