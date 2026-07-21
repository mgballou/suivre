/**
 * WCAG relative-luminance contrast, so the palette rules can be asserted
 * against the real tokens instead of trusted. Test-only: the app never computes
 * contrast at runtime.
 */

function channel(value: number): number {
    const sRgb = value / 255;

    return sRgb <= 0.03928
        ? sRgb / 12.92
        : ((sRgb + 0.055) / 1.055) ** 2.4;
}

export function luminance(hex: string): number {
    const value = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((offset) =>
        Number.parseInt(value.slice(offset, offset + 2), 16),
    );

    return (
        0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
    );
}

export function contrast(a: string, b: string): number {
    const [lighter, darker] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Pull custom properties out of a CSS block so the tests read the tokens the
 * app actually ships rather than a copy that can drift from them.
 */
export function readTokens(css: string, selector: string): Record<string, string> {
    const start = css.indexOf(selector) + selector.length;
    const body = css.slice(start, css.indexOf('}', start));

    return Object.fromEntries(
        [...body.matchAll(/(--[\w-]+):\s*(#[0-9a-f]{6})/gi)].map(
            ([, name, hex]) => [name, hex],
        ),
    );
}
