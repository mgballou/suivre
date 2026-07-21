/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { contrast, luminance, readTokens } from '@/test/contrast';
import { chartConfig, MAX_IDENTITY_SERIES } from './charts';
import { INTENSITY_LEVELS } from './intensity';

// The real shipped stylesheet, so the palette rules are asserted against the
// tokens the app renders rather than a copy that can drift from them.
const CSS = readFileSync('resources/css/app.css', 'utf8');

const LIGHT = readTokens(CSS, ':root {');
const DARK = readTokens(CSS, '.dark {');

/** Charts sit on cards, so the card is the surface contrast is measured against. */
const SURFACE = { light: LIGHT['--card'], dark: DARK['--card'] };

const SERIES_KEYS = ['--series-1', '--series-2', '--series-3', '--series-4'];

/**
 * Two light slots sit under 3:1 by design (magenta 2.69, yellow 2.17). The
 * dataviz relief rule permits that only where identity is also carried another
 * way, which `TrendChart` guarantees by always rendering a legend for an
 * identity chart. 2:1 is the floor below which even a labelled mark stops
 * being locatable.
 */
const MARK_FLOOR = 2;
const MARK_TARGET = 3;

describe('chart tokens', () => {
    it.each(['light', 'dark'] as const)(
        'defines every categorical slot in %s mode',
        (mode) => {
            const tokens = mode === 'light' ? LIGHT : DARK;

            SERIES_KEYS.forEach((key) => {
                expect(tokens[key]).toMatch(/^#[0-9a-f]{6}$/i);
            });
        },
    );

    it.each(['light', 'dark'] as const)(
        'keeps every categorical slot locatable on the %s surface',
        (mode) => {
            const tokens = mode === 'light' ? LIGHT : DARK;

            SERIES_KEYS.forEach((key) => {
                expect(contrast(tokens[key], SURFACE[mode])).toBeGreaterThan(
                    MARK_FLOOR,
                );
            });
        },
    );

    it('clears the full 3:1 mark contrast for every dark slot', () => {
        SERIES_KEYS.forEach((key) => {
            expect(contrast(DARK[key], SURFACE.dark)).toBeGreaterThanOrEqual(
                MARK_TARGET,
            );
        });
    });

    it.each(['light', 'dark'] as const)(
        'keeps axis and legend ink at AA on the %s surface',
        (mode) => {
            const tokens = mode === 'light' ? LIGHT : DARK;

            expect(
                contrast(tokens['--muted-foreground'], SURFACE[mode]),
            ).toBeGreaterThanOrEqual(4.5);
        },
    );

    it.each(['light', 'dark'] as const)(
        'keeps the %s gridline recessive rather than competing with the data',
        (mode) => {
            const tokens = mode === 'light' ? LIGHT : DARK;

            expect(contrast(tokens['--border'], SURFACE[mode])).toBeLessThan(
                1.5,
            );
        },
    );

    it.each(['light', 'dark'] as const)(
        'steps the %s intensity ramp monotonically, so magnitude reads off lightness',
        (mode) => {
            const tokens = mode === 'light' ? LIGHT : DARK;

            const luminances = INTENSITY_LEVELS.map((level) =>
                luminance(tokens[`--intensity-${level}`]),
            );

            const ordered =
                mode === 'light'
                    ? [...luminances].sort((a, b) => b - a)
                    : [...luminances].sort((a, b) => a - b);

            expect(luminances).toEqual(ordered);
        },
    );

    it('leaves petrol out of the categorical slots, because it cannot do identity work', () => {
        const slots = SERIES_KEYS.map((key) => LIGHT[key]);

        expect(slots).not.toContain(LIGHT['--primary']);
        expect(slots).not.toContain(LIGHT['--intensity-5']);
    });
});

describe('chartConfig', () => {
    it('paints a magnitude chart in petrol, the hue the calendar ramp climbs', () => {
        const config = chartConfig({
            kind: 'magnitude',
            series: { key: 'intensity', label: 'Condition intensity' },
        });

        expect(config.intensity.color).toBe('var(--color-primary)');
    });

    it('assigns identity slots in fixed order', () => {
        const config = chartConfig({
            kind: 'identity',
            series: [
                { key: 'joints', label: 'Joints' },
                { key: 'gut', label: 'Gut' },
            ],
        });

        expect(config.joints.color).toBe('var(--color-series-1)');
        expect(config.gut.color).toBe('var(--color-series-2)');
    });

    it('carries the label through for the legend and tooltip', () => {
        const config = chartConfig({
            kind: 'identity',
            series: [{ key: 'gut', label: 'Gut' }],
        });

        expect(config.gut.label).toBe('Gut');
    });

    it('refuses to cycle the palette past the last slot', () => {
        const series = Array.from(
            { length: MAX_IDENTITY_SERIES + 1 },
            (_, index) => ({ key: `s${index}`, label: `Series ${index}` }),
        );

        expect(() => chartConfig({ kind: 'identity', series })).toThrow(
            /at most 4 series/,
        );
    });
});
