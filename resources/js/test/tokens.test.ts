/**
 * The token vocabulary, held to its own numbers.
 *
 * Ported from `someones=pc@apps/web/test/tokens.test.ts` (itself from
 * `character-bible/test/tokens.test.js`), which is an implementation of the
 * house rule behind that lineage: contrast ratios are **recomputed from the
 * token values**, so changing one tells you what it did. A ratio asserted
 * against a number somebody typed once is a test of the typing, and it goes
 * stale the first time somebody edits a color.
 *
 * Adapted for suivre: tokens are authored as hex (so the reader parses hex,
 * and converts to OKLch only where a hue is needed), and the two schemes are
 * `:root` and `.dark` — one dark block, not the source's platform/chosen pair,
 * so the source's "two dark blocks must be the same block" test has no
 * counterpart here.
 *
 * Of the four tests the source port skipped for lacking the vocabulary, two
 * now exist here and are written below: the shadcn surface/foreground pairing
 * convention, and the `--dur-*` tokens under reduced motion. Two still do not
 * apply. `data-slot` — the vendored primitives carry those attributes, but no
 * stylesheet in this repo addresses that seam; styling flows through Tailwind
 * token classes, which the raw-value test below polices. And the hue-free
 * plate ground — there are no plates, and the grounds here are deliberately
 * petrol-tinted (D20/D25), so hue-free is not this system's rule.
 *
 * The `--ramp-ink-*` on `--intensity-*` steps are deliberately not asserted
 * here: `ConditionHueTest` already proves that pairing against these values.
 */

import { expect, test } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { parseColor, parseOklch, contrast, outOfGamut, tokenBlock, blockBody, srgbToOklch } from './oklch';
import type { Measurable } from './oklch';

const ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const tokens = fs.readFileSync(path.join(ROOT, 'css', 'app.css'), 'utf8');

const THEMES = {
    light: ':root {',
    dark: '.dark {',
} as const;

const blocks: Record<string, Map<string, string>> = Object.fromEntries(
    Object.entries(THEMES).map(([name, start]) => [name, tokenBlock(tokens, start)]),
);

const block = (theme: string): Map<string, string> => {
    const found = blocks[theme];
    expect(found, `no token block for ${theme}`).toBeDefined();
    return found;
};

const colors = (theme: string): ((name: string) => Measurable) => {
    const map = block(theme);
    return (name) => {
        const value = map.get(name);
        const parsed = parseColor(value);
        expect(parsed, `${name} in ${theme} is not a color value: ${value}`).not.toBeNull();
        return parsed as Measurable;
    };
};

/** Hue in OKLch degrees, for the checks that need one. */
const hue = (color: Measurable): number => ('srgb' in color ? srgbToOklch(color.srgb).h : color.h);

const themeNames = ['light', 'dark'] as const;

// ---------------------------------------------------------------- the contract

test('every scheme redeclares every color token', () => {
    // The source asserted both theme blocks declare the same set of names.
    // Suivre splits differently by design: scheme-independent values (radius,
    // durations, glass alpha and blur) are declared once on :root, and a token
    // that resolves to a color must be redeclared in .dark — a color themed in
    // only one scheme half-applies, and half-applied is worse than absent.
    const light = block('light');
    const dark = block('dark');
    const unthemed = [...light.entries()]
        .filter(([name, value]) => parseColor(value) && !dark.has(name))
        .map(([name]) => name);
    expect(unthemed, 'color tokens not redeclared in .dark').toEqual([]);
    const strays = [...dark.keys()].filter((name) => !light.has(name));
    expect(strays, 'tokens declared in .dark but not :root').toEqual([]);
});

test('every color token is inside sRGB', () => {
    // Hex cannot leave sRGB, so today this guards only a token authored in
    // oklch — which is exactly the day it starts to matter.
    for (const theme of themeNames) {
        for (const [name, value] of block(theme)) {
            const parsed = parseOklch(value);
            if (!parsed) continue;
            expect(
                outOfGamut(parsed),
                `${name} in ${theme} (${value}) falls outside sRGB and will be silently clamped, ` +
                    'which is a color nobody chose',
            ).toBe(false);
        }
    }
});

// ---------------------------------------------------------------- contrast

/*
 * Text sits on all of these: the page, cards and popovers, the secondary and
 * muted wells, the accent hover wash, the sidebar, the material-layer surfaces
 * (D28), the control-group tint, and the active tab pill (SUI-60).
 */
const SURFACES = [
    '--background',
    '--card',
    '--popover',
    '--secondary',
    '--muted',
    '--accent',
    '--sidebar',
    '--surface-page',
    '--surface-raised',
    '--surface-floating',
    '--panel-tint',
    '--tab-indicator',
] as const;

/*
 * Inks that roam across surfaces rather than belonging to one. --foreground is
 * body ink, --muted-foreground the quiet ink, and --destructive is the error
 * ink — `text-destructive` carries validation messages and destructive labels.
 */
const INK = ['--foreground', '--muted-foreground', '--destructive'] as const;

test('every text ink clears WCAG AA against every surface it can sit on', () => {
    for (const theme of themeNames) {
        const c = colors(theme);
        for (const ink of INK) {
            for (const surface of SURFACES) {
                const ratio = contrast(c(ink), c(surface));
                expect(
                    ratio,
                    `${theme}: ${ink} on ${surface} is ${ratio.toFixed(2)}:1, under the 4.5 floor`,
                ).toBeGreaterThanOrEqual(4.5);
            }
        }
    }
});

test('every shadcn surface/foreground pair clears AA', () => {
    // The shadcn vocabulary this repo does have: every surface names its ink
    // by convention (`--X` carries `--X-foreground`), so the pairing itself is
    // the contract — each label on the surface shadcn puts it on.
    const CORE = [
        '--card',
        '--popover',
        '--primary',
        '--secondary',
        '--muted',
        '--accent',
        '--destructive',
    ] as const;

    for (const theme of themeNames) {
        const map = block(theme);
        const c = colors(theme);
        const pairs = new Set<string>(CORE);
        // Anything else following the convention (the sidebar family) is held
        // to the same rule without being named here.
        for (const name of map.keys()) {
            if (name.endsWith('-foreground') && name !== '--foreground') {
                pairs.add(name.replace(/-foreground$/, ''));
            }
        }
        for (const base of pairs) {
            expect(map.has(`${base}-foreground`), `${theme}: ${base} has no -foreground token`).toBe(
                true,
            );
            const ratio = contrast(c(`${base}-foreground`), c(base));
            expect(
                ratio,
                `${theme}: ${base}-foreground on ${base} is ${ratio.toFixed(2)}:1, under the 4.5 floor`,
            ).toBeGreaterThanOrEqual(4.5);
        }
    }
});

test("a control's edge clears the 3:1 boundary floor, and a seam stays under it", () => {
    // The one contrast assertion that runs the other way. --input draws the
    // boundary of a form control, which WCAG 1.4.11 holds to 3:1; --border
    // draws the hairline between things, and a seam loud enough to read as a
    // boundary is a line doing a control's job.
    const CONTROL_SURFACES = ['--background', '--card', '--popover'] as const;
    for (const theme of themeNames) {
        const c = colors(theme);
        for (const surface of CONTROL_SURFACES) {
            const edge = contrast(c('--input'), c(surface));
            expect(
                edge,
                `${theme}: --input on ${surface} is ${edge.toFixed(2)}:1, under the 3:1 floor ` +
                    'for a control boundary (WCAG 1.4.11)',
            ).toBeGreaterThanOrEqual(3);
        }
        for (const surface of SURFACES) {
            const seam = contrast(c('--border'), c(surface));
            expect(
                seam,
                `${theme}: --border on ${surface} is ${seam.toFixed(2)}:1, above the hairline ceiling`,
            ).toBeLessThan(3);
        }
    }
});

test('the focus ring stays visible against every surface it floats over', () => {
    // The source asserted one outline declaration built from the accent; this
    // repo has no outline system — focus is shadcn's focus-visible ring, drawn
    // from --ring by classes across the primitives. The recomputable local
    // invariant is that the ring is visible where it appears.
    for (const theme of themeNames) {
        const c = colors(theme);
        for (const surface of ['--background', '--card', '--popover'] as const) {
            const ratio = contrast(c('--ring'), c(surface));
            expect(
                ratio,
                `${theme}: --ring on ${surface} is ${ratio.toFixed(2)}:1, under the 3:1 floor ` +
                    'for a focus indicator',
            ).toBeGreaterThanOrEqual(3);
        }
    }
});

test('no signal tone sits within 45 degrees of the primary', () => {
    // Two tones a person cannot tell apart are one tone carrying two meanings,
    // and the primary is the one that must win. The series hues are exempt:
    // they are data, and they are never the primary's job. In this vocabulary
    // the signal tone is --destructive and the app's hue is --primary.
    for (const theme of themeNames) {
        const c = colors(theme);
        const primary = hue(c('--primary'));
        const d = Math.abs(((hue(c('--destructive')) - primary + 540) % 360) - 180);
        expect(
            d,
            `${theme}: --destructive is ${d.toFixed(0)} degrees from --primary, under the 45 floor`,
        ).toBeGreaterThanOrEqual(45);
    }
});

// ---------------------------------------------------------------- raw values

test('nothing outside the token definition names a raw value', () => {
    const raw = /#[0-9a-fA-F]{3,8}\b|oklch\(|rgba?\(|hsla?\(/;
    const walk = (dir: string): readonly string[] =>
        fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
            const full = path.join(dir, entry.name);
            if (entry.isDirectory()) return walk(full);
            return /\.(ts|tsx|css)$/.test(entry.name) ? [full] : [];
        });

    // Vendored shadcn primitives are kept unmodified; the raw values in them
    // quote recharts' own inline defaults inside attribute selectors to
    // override them — they select, they do not paint.
    const roots = [path.join(ROOT, 'js'), path.join(ROOT, '..', 'css')];
    const tokenFile = path.join(ROOT, '..', 'css', 'app.css');
    const files = roots.flatMap((dir) => (fs.existsSync(dir) ? walk(dir) : []));

    for (const file of files) {
        if (file === tokenFile) continue;
        if (file.includes(`${path.sep}ui${path.sep}`)) continue;
        if (/\.test\.[jt]sx?$/.test(file)) continue;
        fs.readFileSync(file, 'utf8')
            .split('\n')
            .forEach((line, i) => {
                expect(
                    raw.test(line),
                    `${path.relative(ROOT, file)}:${i + 1} names a raw color, which the token ` +
                        `layer exists to prevent:\n  ${line.trim()}`,
                ).toBe(false);
            });
    }
});

// ---------------------------------------------------------------- motion

test('reduced motion is honored where the animations are declared, on the duration tokens', () => {
    // The source's blanket `transition-duration: 0.01ms !important` override
    // does not fit this design: reduced motion here keeps the feedback and
    // drops the displacement, so the block re-points every animated class at a
    // quiet fade. What must hold is the coverage — no class can gain an
    // animation that escapes the preference — and that the durations it runs
    // on are the `--dur-*` tokens, not numbers typed at the override.
    const components = blockBody(tokens, '@layer components');
    const reduced = blockBody(tokens, '@media (prefers-reduced-motion: reduce)');

    const animatedIn = (body: string): Set<string> => {
        const names = new Set<string>();
        for (const m of body.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
            const [, selector, ruleBody] = m;
            if (selector === undefined || ruleBody === undefined) continue;
            if (!ruleBody.includes('animation:')) continue;
            for (const cls of selector.matchAll(/\.([a-z0-9-]+)/g)) {
                if (cls[1] !== undefined) names.add(cls[1]);
            }
        }
        return names;
    };

    const animated = animatedIn(components);
    expect(animated, 'no component classes found to check').not.toHaveLength(0);
    const covered = animatedIn(reduced);
    const escaped = [...animated].filter((cls) => !covered.has(cls));
    expect(
        escaped,
        'classes animated outside the reduced-motion block but not re-addressed inside it',
    ).toEqual([]);

    for (const m of reduced.matchAll(/animation:\s*([^;]+);/g)) {
        const value = m[1] ?? '';
        expect(
            value,
            `reduced-motion animation "${value.trim()}" runs on a typed duration, not a --dur-* token`,
        ).toMatch(/var\(--dur-/);
    }
});
