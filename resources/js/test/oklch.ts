/**
 * Token colors → sRGB → the WCAG contrast ratio between two of them.
 *
 * This exists so `tokens.test.ts` can recompute every contrast ratio from the
 * token values themselves. A ratio asserted against a number somebody typed
 * once is a test of the typing.
 *
 * Ported from `someones=pc@apps/web/test/oklch.ts` (itself from
 * `character-bible/test/oklch.js`), adapted for this repo: suivre authors its
 * tokens as hex, not oklch, so hex is parsed natively and an sRGB→OKLch
 * inverse is included for the checks that need a hue or a chroma. Stdlib only,
 * and deliberately so: a color library here would be a dependency the
 * assertion does not need and a second opinion about what the numbers are.
 */

export interface Oklch {
    readonly l: number
    readonly c: number
    readonly h: number
    readonly alpha: number
}

export interface Srgb {
    readonly r: number
    readonly g: number
    readonly b: number
}

/** A color already composited down to sRGB, so it carries no hue to move. */
export interface Composited {
    readonly srgb: Srgb
}

export type Measurable = Oklch | Composited

const isComposited = (x: Measurable): x is Composited => 'srgb' in x

/** `oklch(62% 0.155 52)` or `oklch(62% 0.155 52 / 0.12)` → `{ l, c, h, alpha }`. */
export function parseOklch(text: string | undefined): Oklch | null {
    if (text === undefined) return null
    const m = /^oklch\(\s*([\d.]+)%\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+))?\s*\)$/.exec(
        String(text).trim(),
    )
    if (!m) return null
    const [, l, c, h, alpha] = m
    return {
        l: Number(l) / 100,
        c: Number(c),
        h: Number(h),
        alpha: alpha === undefined ? 1 : Number(alpha),
    }
}

/**
 * `#fbfbfa` → `{ r, g, b }`, 0–255 per channel. Three- and six-digit forms
 * only: an alpha suffix would make the value a wash over a ground rather than
 * a color, and no such token exists to composite here.
 */
export function parseHex(text: string | undefined): Srgb | null {
    if (text === undefined) return null
    const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(text).trim())
    if (!m) return null
    const digits = m[1] ?? ''
    const full =
        digits.length === 3
            ? digits
                  .split('')
                  .map((d) => d + d)
                  .join('')
            : digits
    return {
        r: parseInt(full.slice(0, 2), 16),
        g: parseInt(full.slice(2, 4), 16),
        b: parseInt(full.slice(4, 6), 16),
    }
}

/** Any single-color token value this stylesheet can hold: hex today, oklch if ever authored. */
export function parseColor(text: string | undefined): Measurable | null {
    const hex = parseHex(text)
    if (hex) return { srgb: hex }
    return parseOklch(text)
}

const clamp01 = (x: number): number => (x < 0 ? 0 : x > 1 ? 1 : x)

interface LinearRgb {
    readonly r: number
    readonly g: number
    readonly b: number
}

/** Linear-light sRGB, unclamped, so out-of-gamut shows up as a value past 1. */
export function oklchToLinearRgb({ l, c, h }: Oklch): LinearRgb {
    const rad = (h * Math.PI) / 180
    const a = c * Math.cos(rad)
    const b = c * Math.sin(rad)

    const l_ = l + 0.3963377774 * a + 0.2158037573 * b
    const m_ = l - 0.1055613458 * a - 0.0638541728 * b
    const s_ = l - 0.0894841775 * a - 1.291485548 * b

    const L = l_ * l_ * l_
    const M = m_ * m_ * m_
    const S = s_ * s_ * s_

    return {
        r: 4.0767416621 * L - 3.3077115913 * M + 0.2309699292 * S,
        g: -1.2684380046 * L + 2.6097574011 * M - 0.3413193965 * S,
        b: -0.0041960863 * L - 0.7034186147 * M + 1.707614701 * S,
    }
}

const encode = (x: number): number =>
    x <= 0.0031308 ? 12.92 * x : 1.055 * Math.pow(clamp01(x), 1 / 2.4) - 0.055

const decode = (x: number): number => (x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4))

const toBytes = (lin: LinearRgb): Srgb => ({
    r: Math.round(clamp01(encode(lin.r)) * 255),
    g: Math.round(clamp01(encode(lin.g)) * 255),
    b: Math.round(clamp01(encode(lin.b)) * 255),
})

/** 0–255 per channel, clamped into gamut. */
export function oklchToSrgb(color: Oklch): Srgb {
    return toBytes(oklchToLinearRgb(color))
}

/**
 * The inverse direction: a hex token into OKLch, so hue and chroma can be
 * checked on the values this repo actually authors. Matrices per Ottosson's
 * OKLab spec, the same source the forward direction above follows.
 */
export function srgbToOklch({ r, g, b }: Srgb): Oklch {
    const rr = decode(r / 255)
    const gg = decode(g / 255)
    const bb = decode(b / 255)

    const L = 0.41239079926595934 * rr + 0.357584339383878 * gg + 0.1804807884018343 * bb
    const M = 0.21263900587151027 * rr + 0.715168678767756 * gg + 0.07219231536073371 * bb
    const S = 0.01933081871559182 * rr + 0.11919477979462598 * gg + 0.9505321522496607 * bb

    const l_ = Math.cbrt(L)
    const m_ = Math.cbrt(M)
    const s_ = Math.cbrt(S)

    const l = 0.2104542553 * l_ + 0.793617785 * m_ - 0.0040720468 * s_
    const a = 1.9779984951 * l_ - 2.428592205 * m_ + 0.4505937099 * s_
    const bb2 = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.808675766 * s_

    return {
        l,
        c: Math.hypot(a, bb2),
        h: (((Math.atan2(bb2, a) * 180) / Math.PI + 360) % 360),
        alpha: 1,
    }
}

/** True when any channel falls outside sRGB before clamping. */
export function outOfGamut(color: Oklch): boolean {
    const { r, g, b } = oklchToLinearRgb(color)
    const slack = 1e-4
    return [r, g, b].some((x) => x < -slack || x > 1 + slack)
}

const luminanceChannel = (v: number): number => {
    const x = v / 255
    return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4)
}

const luminance = (x: Measurable): number => {
    const { r, g, b } = isComposited(x) ? x.srgb : oklchToSrgb(x)
    return 0.2126 * luminanceChannel(r) + 0.7152 * luminanceChannel(g) + 0.0722 * luminanceChannel(b)
}

/** WCAG 2.2 contrast ratio, 1 to 21. */
export function contrast(a: Measurable, b: Measurable): number {
    const la = luminance(a)
    const lb = luminance(b)
    const [hi, lo] = la > lb ? [la, lb] : [lb, la]
    return (hi + 0.05) / (lo + 0.05)
}

/**
 * The raw text of one block, from its opening brace to the matching close.
 * `start` is a string that opens the block; the block ends at its matching
 * brace.
 */
export function blockBody(css: string, start: string): string {
    const at = css.indexOf(start)
    if (at === -1) throw new Error(`no block opening with ${JSON.stringify(start)}`)
    let depth = 0
    let i = at + start.length - 1
    const open = i
    for (; i < css.length; i += 1) {
        if (css[i] === '{') depth += 1
        else if (css[i] === '}') {
            depth -= 1
            if (depth === 0) break
        }
    }
    return css.slice(open, i)
}

/**
 * Every `--name: value;` declaration inside one block of a stylesheet, keyed by
 * name.
 */
export function tokenBlock(css: string, start: string): Map<string, string> {
    const out = new Map<string, string>()
    for (const m of blockBody(css, start).matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/gi)) {
        const [, name, value] = m
        if (name !== undefined && value !== undefined) out.set(name, value.trim())
    }
    return out
}
