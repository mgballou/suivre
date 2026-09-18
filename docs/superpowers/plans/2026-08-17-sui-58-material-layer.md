# SUI-58 — Material Layer Implementation Plan

- **Status:** Active
- **Ticket:** SUI-58, Linear project *Suivre v1*, milestone *V1 — Interface depth*
- **Spec:** `docs/superpowers/specs/2026-08-16-interface-depth-design.md` §3–4
- **Stack position:** base of the stack. SUI-59 and SUI-60 branch off this one.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a tokenized material layer — three elevation steps, a glass surface, a panel tint and a gooey SVG filter — to `resources/css/app.css`, proven correct by computation rather than by eye, and record the decision-log entry that stops the next agent reverting it as "ornament".

**Architecture:** Tokens are authored once in `app.css` beside the existing `--dur-*`, `--ease-quiet` and radius scale. A test-support class declares what those tokens must be and a Pest feature test proves (a) `app.css` declares exactly those values, and (b) every text/surface pair clears WCAG AA — for glass, against the *composited* result over the worst backdrop it can sit over, not against its nominal background. No component consumes the tokens in this ticket; SUI-59 and SUI-60 do that.

**Tech Stack:** Tailwind v4 (`@theme`, `@layer utilities`), CSS custom properties, `backdrop-filter`, an inline SVG `<filter>`, Pest 4, PHP 8.4.

## Global Constraints

Copied from the spec and the ticket. Every task's requirements implicitly include these.

- **Every D20 commitment survives.** No red, no streaks or rings, no praise copy, no celebratory motion, tabular figures, 8px radius (`--radius: 0.5rem`), one easing token.
- **The test for any treatment:** does it say something about **structure**, or something about the **user's performance**? Structure is in scope. Performance stays prohibited.
- **Elevation is never a shadow alone** — each step is a shadow token *plus* a background token, because a shadow alone reads as grime in dark mode.
- **Glass only on things that genuinely overlay content.** Glass over nothing is a lie about depth.
- **Gooey is tuned soft** — felt at the boundary, invisible at rest. If it reads as playful it is turned up too far.
- **`prefers-reduced-motion` removes travel; the static filter may stay.**
- **`backdrop-filter` unsupported falls back to opaque at the same elevation, never to unreadable.**
- **Prove the contrast; do not look at it.** D25 exists because a ramp was approved by eye and shipped two contrast defects.
- **Toolchain:** `herd php`, `herd composer` — never bare `php`/`composer`.
- **No bespoke values.** A component reaching for a raw blur radius or a hand-written shadow is a review failure.

## Why the mirror lives in `tests/Support/`, not an enum

`ConditionHue` and `RampStep` hold their ramps in PHP because server code genuinely consumes them — a `Condition` has a hue, and `RampStep::fromRating()` buckets a rating server-side. Nothing on the server consumes an elevation step. Inventing `App\Enums\Elevation` would create a domain primitive with no domain, which the architecture rules rule out. So `app.css` is the source of truth and `Tests\Support\Material` is the assertion of what it must contain — the same two-homes-must-agree check `ConditionHueTest::cssVariable()` already performs, pointed the other way.

## File structure

| File | Responsibility |
|---|---|
| `resources/css/app.css` (modify) | The tokens themselves. The only home of a real value. |
| `resources/js/components/suivre/gooey-filter.tsx` (create) | The one `<svg><filter id="gooey">` instance, mounted once in the app shell. Nothing else. |
| `resources/js/layouts/app-layout.tsx` (modify) | Mounts `<GooeyFilter />` so any descendant can reference `url(#gooey)`. |
| `tests/Support/Stylesheet.php` (create) | Reads one custom property out of `app.css` by selector. Extracted from `ConditionHueTest`, which then uses it. |
| `tests/Support/Material.php` (create) | Declares the authored token values and the backdrops glass must survive. |
| `tests/Support/Wcag.php` (modify) | Gains `composite()` — alpha-blend two hexes — so a glass surface's effective colour can be measured. |
| `tests/Feature/Design/MaterialLayerTest.php` (create) | Proves `app.css` matches `Material`, and every pair clears AA. |
| `tests/Feature/Enums/ConditionHueTest.php` (modify) | Drops its local `cssVariable()` in favour of `Stylesheet::hex()`. Assertions unchanged. |
| `docs/decisions/decision-log.md` (modify) | D28. |
| `.ai/guidelines/front-end-conventions.blade.php` (modify) | Records that elevation/glass/tint/gooey are tokens and a bespoke value is a review failure. |

---

### Task 1: `Stylesheet` support class, and `ConditionHueTest` uses it

Pure refactor, no behaviour change. Do it first so Task 3 has something to build on and the diff that follows is only new work.

**Files:**
- Create: `tests/Support/Stylesheet.php`
- Modify: `tests/Feature/Enums/ConditionHueTest.php` (delete the local `cssVariable()` function at the bottom; replace its 3 call sites)

**Interfaces:**
- Produces: `Tests\Support\Stylesheet::hex(string $selector, string $property): string` and `Tests\Support\Stylesheet::raw(string $selector, string $property): string`. `hex()` keeps `ConditionHueTest`'s exact current semantics (matches `#rrggbb`, throws `RuntimeException` when absent). `raw()` returns the whole declaration value verbatim, for shadows and blur radii that are not hexes.

- [ ] **Step 1: Run the existing suite for this file so you know it is green before you touch it**

```bash
herd php artisan test --compact tests/Feature/Enums/ConditionHueTest.php
```

Expected: PASS.

- [ ] **Step 2: Create the support class**

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * One custom property as authored in app.css.
 *
 * The stylesheet is the second home of every design value, so the pair has to
 * be checked rather than trusted: a value corrected in PHP but not in CSS would
 * leave the suite green while the app rendered the old one.
 */
final class Stylesheet
{
    /** A `#rrggbb` value. */
    public static function hex(string $selector, string $property): string
    {
        return self::read($selector, $property, '(#[0-9a-f]{6})');
    }

    /** Any value — shadows, lengths, filter functions. Returned verbatim. */
    public static function raw(string $selector, string $property): string
    {
        return self::read($selector, $property, '([^;]+)');
    }

    private static function read(string $selector, string $property, string $pattern): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/app.css')));

        preg_match_all('/(?<selector>[^{}]+)\{(?<body>[^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $lines = array_filter(array_map(trim(...), explode("\n", $block['selector'])));

            if (end($lines) !== $selector) {
                continue;
            }

            if (preg_match('/' . preg_quote($property, '/') . ':\s*' . $pattern . ';/', $block['body'], $value) === 1) {
                return trim($value[1]);
            }
        }

        throw new RuntimeException("app.css declares no {$property} on {$selector}.");
    }
}
```

- [ ] **Step 3: Replace the call sites in `ConditionHueTest`**

Add `use Tests\Support\Stylesheet;` to the imports. Delete the entire `function cssVariable(...)` block and its doc comment from the bottom of the file. Then swap the three calls:

```php
expect(Stylesheet::hex($selector, "--condition-{$step}"))->toBe($swatch);
```

```php
expect(Stylesheet::hex($selector, "--intensity-{$step->value}"))->toBe($step->petrol($scheme));
expect(Stylesheet::hex($selector, "--ramp-ink-{$step->value}"))->toBe($step->ink($scheme));
```

- [ ] **Step 4: Run it — the refactor must be invisible**

```bash
herd php artisan test --compact tests/Feature/Enums/ConditionHueTest.php
```

Expected: PASS, same test count as Step 1.

- [ ] **Step 5: Commit**

```bash
git add tests/Support/Stylesheet.php tests/Feature/Enums/ConditionHueTest.php
git commit --no-gpg-sign -m "Extract the app.css reader into Tests\\Support\\Stylesheet"
```

> **On `--no-gpg-sign`:** the maintainer's 1Password vault will be locked for the duration of this work. Signing is a publication concern, not a correctness one — commit unsigned and let the maintainer re-sign the branch in one pass. Never flip `commit.gpgsign` in config.

---

### Task 2: `Wcag::composite()`

A glass surface's effective colour is its own colour alpha-blended over whatever is behind it. Without this, "AA against the worst backdrop" cannot be computed.

**Files:**
- Modify: `tests/Support/Wcag.php`
- Test: `tests/Feature/Support/WcagTest.php` (create)

**Interfaces:**
- Produces: `Tests\Support\Wcag::composite(string $over, string $under, float $alpha): string` — returns `#rrggbb`. `$alpha` is the opacity of `$over`. `composite($x, $y, 1.0) === $x`; `composite($x, $y, 0.0) === $y`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\Support\Wcag;

it('returns the top colour at full opacity', function (): void {
    expect(Wcag::composite('#3f7d7b', '#ffffff', 1.0))->toBe('#3f7d7b');
});

it('returns the backdrop at zero opacity', function (): void {
    expect(Wcag::composite('#3f7d7b', '#ffffff', 0.0))->toBe('#ffffff');
});

it('blends each channel linearly in sRGB space', function (): void {
    expect(Wcag::composite('#000000', '#ffffff', 0.5))->toBe('#808080');
});

it('rounds each channel rather than truncating it', function (): void {
    // 0.8 * 27 + 0.2 * 237 = 69.0 exactly; 0.8 * 29 + 0.2 * 238 = 70.8 -> 71.
    expect(Wcag::composite('#1b1c1d', '#edeeee', 0.8))->toBe('#454647');
});
```

- [ ] **Step 2: Run it and watch it fail**

```bash
herd php artisan test --compact tests/Feature/Support/WcagTest.php
```

Expected: FAIL — `Call to undefined method Tests\Support\Wcag::composite()`.

- [ ] **Step 3: Implement it**

Add to `Tests\Support\Wcag`, above the private helpers. Note it uses raw 0–255 channels, not the linearised ones `luminance()` needs, so it does its own unpacking rather than reusing `channels()` (which divides by 255).

```php
    /**
     * `$over` laid on `$under` at `$alpha` opacity — what a translucent surface
     * actually renders as. Contrast is measured against this, never against the
     * glass token's nominal colour, because a user reads the composite.
     */
    public static function composite(string $over, string $under, float $alpha): string
    {
        $top = self::bytes($over);
        $bottom = self::bytes($under);

        return sprintf(
            '#%02x%02x%02x',
            ...array_map(
                static fn (int $a, int $b): int => (int) round($alpha * $a + (1 - $alpha) * $b),
                $top,
                $bottom,
            ),
        );
    }

    /**
     * @return array<int, int>
     */
    private static function bytes(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }
```

- [ ] **Step 4: Run it and watch it pass**

```bash
herd php artisan test --compact tests/Feature/Support/WcagTest.php
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add tests/Support/Wcag.php tests/Feature/Support/WcagTest.php
git commit --no-gpg-sign -m "Add Wcag::composite() so a translucent surface can be measured as rendered"
```

---

### Task 3: The tokens, and the test that proves them

The heart of the ticket. Values below are final — they were computed against the WCAG formulas before this plan was written, not chosen by eye. Do not adjust them without re-running the contrast test.

**Files:**
- Create: `tests/Support/Material.php`
- Create: `tests/Feature/Design/MaterialLayerTest.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `Tests\Support\Stylesheet` (Task 1), `Tests\Support\Wcag::composite()` (Task 2).
- Produces: the CSS custom properties `--surface-page`, `--surface-raised`, `--surface-floating`, `--shadow-raised`, `--shadow-floating`, `--panel-tint`, `--glass-surface`, `--glass-alpha`, `--glass-blur`, `--glass-border`, `--tab-indicator`, and the utility classes `.elevation-raised`, `.elevation-floating`, `.panel-tint`, `.glass`. SUI-59 and SUI-60 consume these by name.

#### The values

| Token | Light | Dark | What it is |
|---|---|---|---|
| `--surface-page` | `#fbfbfa` | `#131314` | The page. Same as `--background`; named separately so a component asks for an elevation, not a background. |
| `--surface-raised` | `#ffffff` | `#1b1c1d` | A card on the page. |
| `--surface-floating` | `#ffffff` | `#222324` | A sheet, popover or the tab bar. In dark it lifts again, because dark elevation is carried by the surface and not the shadow. |
| `--panel-tint` | `#f6f7f6` | `#191a1b` | Behind a group of form controls. Faint — a group without a box drawn round it. |
| `--glass-surface` | `#fbfbfa` | `#1b1c1d` | The glass fill, before alpha. |
| `--glass-border` | `#e4e7e6` | `#2a2e2e` | The hairline. Same as `--border`, named for the same reason `--surface-page` is. |
| `--tab-indicator` | `#e2eeee` | `#262a2a` | The active-tab pill SUI-60 builds. Opaque, so it is immune to whatever scrolls under the bar. |

Scheme-independent: `--glass-alpha: 0.8`, `--glass-blur: 12px`.

Shadows — two steps, both low-spread and soft. Light casts the app's own ink; dark casts black, because a tinted shadow on a near-black surface reads as a colour cast.

```
light  --shadow-raised:   0 1px 2px 0 rgb(22 32 31 / 0.04), 0 1px 3px 0 rgb(22 32 31 / 0.06)
light  --shadow-floating: 0 4px 6px -2px rgb(22 32 31 / 0.06), 0 12px 24px -4px rgb(22 32 31 / 0.10)
dark   --shadow-raised:   0 1px 2px 0 rgb(0 0 0 / 0.30), 0 1px 3px 0 rgb(0 0 0 / 0.24)
dark   --shadow-floating: 0 4px 6px -2px rgb(0 0 0 / 0.36), 0 12px 24px -4px rgb(0 0 0 / 0.44)
```

#### The worst backdrop

Glass must clear AA against whatever can scroll under it. The worst case is not a ramp swatch — it is body text, which is the most extreme luminance in either scheme:

- **Light:** ink is dark (`#16201f`), so the worst backdrop is the darkest thing available — `--foreground` `#16201f` itself. Composite at α=0.8 is `#cdcfce`; against `#16201f` that measures **13.7:1**.
- **Dark:** ink is light (`#edeeee`), so the worst backdrop is the lightest — `--foreground` `#edeeee`. Composite at α=0.8 is `#454647`; against `#edeeee` that measures **7.56:1**.

Both clear AA with room. The test asserts this rather than restating it.

- [ ] **Step 1: Write the support class**

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ColorScheme;

/**
 * The material layer as authored in app.css.
 *
 * app.css is the source of truth; this is the assertion of what it must
 * contain, so a token cannot be edited in the stylesheet without a test
 * noticing. There is no App\Enums equivalent because no server code consumes an
 * elevation step — an enum here would be a domain primitive with no domain.
 */
final class Material
{
    public const float GLASS_ALPHA = 0.8;

    public const string GLASS_BLUR = '12px';

    /** @return array<string, string> token name (without leading dashes) => hex */
    public static function surfaces(ColorScheme $scheme): array
    {
        return $scheme->isLight()
            ? [
                'surface-page' => '#fbfbfa',
                'surface-raised' => '#ffffff',
                'surface-floating' => '#ffffff',
                'panel-tint' => '#f6f7f6',
                'glass-surface' => '#fbfbfa',
                'glass-border' => '#e4e7e6',
                'tab-indicator' => '#e2eeee',
            ]
            : [
                'surface-page' => '#131314',
                'surface-raised' => '#1b1c1d',
                'surface-floating' => '#222324',
                'panel-tint' => '#191a1b',
                'glass-surface' => '#1b1c1d',
                'glass-border' => '#2a2e2e',
                'tab-indicator' => '#262a2a',
            ];
    }

    /** The ink a surface in this scheme carries. */
    public static function ink(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#16201f' : '#edeeee';
    }

    /** The quieter ink — labels, counts, the collapsed summary of a card. */
    public static function mutedInk(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#55625f' : '#9ba6a4';
    }

    /**
     * The worst thing that can pass under a glass surface. In light the ink is
     * dark, so the darkest backdrop is worst; in dark the ink is light, so the
     * lightest is. Body text is the extreme in both directions — a ramp swatch
     * never reaches it.
     */
    public static function worstBackdrop(ColorScheme $scheme): string
    {
        return $scheme->isLight() ? '#16201f' : '#edeeee';
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use App\Enums\ColorScheme;
use Tests\Support\Material;
use Tests\Support\Stylesheet;
use Tests\Support\Wcag;

dataset('schemes', function (): iterable {
    foreach (ColorScheme::ordered() as $scheme) {
        yield $scheme->value => [$scheme];
    }
});

function selector(ColorScheme $scheme): string
{
    return $scheme->isLight() ? ':root' : '.dark';
}

it('ships the surface tokens the stylesheet renders', function (ColorScheme $scheme): void {
    foreach (Material::surfaces($scheme) as $token => $hex) {
        expect(Stylesheet::hex(selector($scheme), "--{$token}"))->toBe($hex);
    }
})->with('schemes');

it('keeps the page surface identical to the scheme the ramps are built against', function (ColorScheme $scheme): void {
    expect(Material::surfaces($scheme)['surface-page'])->toBe($scheme->background());
})->with('schemes');

it('declares a shadow for each elevation step above the page', function (ColorScheme $scheme): void {
    expect(Stylesheet::raw(selector($scheme), '--shadow-raised'))->not->toBe('');
    expect(Stylesheet::raw(selector($scheme), '--shadow-floating'))->not->toBe('');
})->with('schemes');

it('declares the glass alpha and blur once, outside either scheme', function (): void {
    expect(Stylesheet::raw(':root', '--glass-alpha'))->toBe((string) Material::GLASS_ALPHA);
    expect(Stylesheet::raw(':root', '--glass-blur'))->toBe(Material::GLASS_BLUR);
});

it('carries AA-legible ink on every opaque surface', function (ColorScheme $scheme): void {
    foreach (['surface-page', 'surface-raised', 'surface-floating', 'panel-tint', 'tab-indicator'] as $token) {
        $surface = Material::surfaces($scheme)[$token];

        expect(Wcag::contrast($surface, Material::ink($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "{$token} ink ({$scheme->value})");
        expect(Wcag::contrast($surface, Material::mutedInk($scheme)))
            ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "{$token} muted ink ({$scheme->value})");
    }
})->with('schemes');

it('carries AA-legible ink on glass over the worst backdrop it can sit above', function (ColorScheme $scheme): void {
    $rendered = Wcag::composite(
        Material::surfaces($scheme)['glass-surface'],
        Material::worstBackdrop($scheme),
        Material::GLASS_ALPHA,
    );

    expect(Wcag::contrast($rendered, Material::ink($scheme)))
        ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "glass ink ({$scheme->value})");
    expect(Wcag::contrast($rendered, Material::mutedInk($scheme)))
        ->toBeGreaterThanOrEqual(Wcag::AA_SMALL_TEXT, "glass muted ink ({$scheme->value})");
})->with('schemes');

it('lifts each elevation step above the one below it', function (ColorScheme $scheme): void {
    $surfaces = Material::surfaces($scheme);

    $page = Wcag::luminance($surfaces['surface-page']);
    $raised = Wcag::luminance($surfaces['surface-raised']);
    $floating = Wcag::luminance($surfaces['surface-floating']);

    $scheme->isLight()
        ? expect($raised)->toBeGreaterThanOrEqual($page)
        : expect($raised)->toBeGreaterThan($page);

    expect($floating)->toBeGreaterThanOrEqual($raised);
})->with('schemes');

it('falls back to the floating surface where backdrop-filter is unsupported', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@supports not (backdrop-filter: blur(1px))');
});
```

- [ ] **Step 3: Run it and watch it fail**

```bash
herd php artisan test --compact tests/Feature/Design/MaterialLayerTest.php
```

Expected: FAIL — `app.css declares no --surface-page on :root`.

- [ ] **Step 4: Add the tokens to `app.css`**

In the `:root` block, immediately after the four `--dur-*` declarations, append:

```css
    /*
     * Material (D28). Depth here is structure, never reward: a surface says
     * "this is a distinct thing" and "this is above that", which is the same
     * category of statement as a border. Three steps and no more.
     */
    --surface-page: #fbfbfa;
    --surface-raised: #ffffff;
    --surface-floating: #ffffff;

    /*
     * Shadow always ships with a surface, never alone. In light the shadow does
     * most of the lifting and casts the app's own ink rather than pure black.
     */
    --shadow-raised: 0 1px 2px 0 rgb(22 32 31 / 0.04), 0 1px 3px 0 rgb(22 32 31 / 0.06);
    --shadow-floating: 0 4px 6px -2px rgb(22 32 31 / 0.06), 0 12px 24px -4px rgb(22 32 31 / 0.10);

    /* A group of controls reads as a group without a box drawn round it. */
    --panel-tint: #f6f7f6;

    /* Glass. Alpha and blur are scheme-independent; the fill is not. */
    --glass-surface: #fbfbfa;
    --glass-border: #e4e7e6;
    --glass-alpha: 0.8;
    --glass-blur: 12px;

    /* The active-tab pill (SUI-60). Opaque, so what scrolls under cannot reach it. */
    --tab-indicator: #e2eeee;
```

In the `.dark` block, at the end, append:

```css
    /*
     * Dark elevation is carried by the surface, not the shadow — a shadow alone
     * on near-black reads as grime, so each step lightens as well as casts.
     */
    --surface-page: #131314;
    --surface-raised: #1b1c1d;
    --surface-floating: #222324;

    --shadow-raised: 0 1px 2px 0 rgb(0 0 0 / 0.30), 0 1px 3px 0 rgb(0 0 0 / 0.24);
    --shadow-floating: 0 4px 6px -2px rgb(0 0 0 / 0.36), 0 12px 24px -4px rgb(0 0 0 / 0.44);

    --panel-tint: #191a1b;

    --glass-surface: #1b1c1d;
    --glass-border: #2a2e2e;

    --tab-indicator: #262a2a;
```

- [ ] **Step 5: Add the utilities**

Append a new block immediately after the existing `@layer utilities { .bg-condition-* }` block. These are authored as utilities rather than `@theme` colours for the reason already recorded for `--condition-*`: a theme token's own `var()` resolves where the token is declared, and these are redeclared on `.dark`. `.dark` lands on `<html>`, so a theme token would in fact work here — but keeping the whole material layer in one authored block is what makes "no bespoke values" reviewable at a glance.

```css
/*
 * The material layer. Elevation is always a surface *and* a shadow; asking for
 * one without the other is the mistake these classes exist to prevent.
 */
@layer utilities {
    .elevation-raised {
        background-color: var(--surface-raised);
        box-shadow: var(--shadow-raised);
    }

    .elevation-floating {
        background-color: var(--surface-floating);
        box-shadow: var(--shadow-floating);
    }

    .panel-tint {
        background-color: var(--panel-tint);
    }

    /*
     * Only for something that genuinely overlays content. Glass over nothing is
     * a lie about depth and looks like one.
     */
    .glass {
        background-color: color-mix(
            in srgb,
            var(--glass-surface) calc(var(--glass-alpha) * 100%),
            transparent
        );
        backdrop-filter: blur(var(--glass-blur));
        border-color: var(--glass-border);
    }
}

/*
 * Without backdrop-filter the translucency has nothing to blur against and the
 * surface reads as washed-out rather than as glass, so it falls back to opaque
 * at the same elevation. It must never fall back to unreadable.
 */
@supports not (backdrop-filter: blur(1px)) {
    .glass {
        background-color: var(--surface-floating);
    }
}
```

- [ ] **Step 6: Run the test and watch it pass**

```bash
herd php artisan test --compact tests/Feature/Design/MaterialLayerTest.php
```

Expected: PASS. If a contrast assertion fails, the token is wrong — fix the token, never the threshold.

- [ ] **Step 7: Commit**

```bash
git add resources/css/app.css tests/Support/Material.php tests/Feature/Design/MaterialLayerTest.php
git commit --no-gpg-sign -m "Add the material layer tokens, proven against WCAG AA in both schemes"
```

---

### Task 4: The gooey filter primitive

The filter has to exist in the DOM for `filter: url(#gooey)` to resolve. One instance, mounted at the app shell, referenced by id.

**Files:**
- Create: `resources/js/components/suivre/gooey-filter.tsx`
- Modify: `resources/js/layouts/app-layout.tsx`
- Test: `resources/js/components/suivre/gooey-filter.test.tsx` (create)

**Interfaces:**
- Produces: `GooeyFilter` (default-exported named export `GooeyFilter`), and the filter id `gooey`, referenced elsewhere as `filter: url(#gooey)`. SUI-60 consumes it.

- [ ] **Step 1: Read the layout before you edit it**

```bash
herd php artisan test --compact resources/js 2>/dev/null || npx vitest run --reporter=dot
```

Read `resources/js/layouts/app-layout.tsx` and find where the layout's outermost element is. `<GooeyFilter />` mounts as its first child.

- [ ] **Step 2: Write the failing test**

```tsx
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { GooeyFilter } from './gooey-filter';

describe('GooeyFilter', () => {
    it('exposes a filter the material layer can reference by id', () => {
        const { container } = render(<GooeyFilter />);

        expect(container.querySelector('filter#gooey')).not.toBeNull();
    });

    it('is hidden from assistive technology and takes no space', () => {
        const { container } = render(<GooeyFilter />);
        const svg = container.querySelector('svg');

        expect(svg?.getAttribute('aria-hidden')).toBe('true');
        expect(svg?.getAttribute('width')).toBe('0');
        expect(svg?.getAttribute('height')).toBe('0');
    });

    it('contrasts alpha tightly enough that the merge is felt, not seen', () => {
        const { container } = render(<GooeyFilter />);
        const matrix = container.querySelector('feColorMatrix');

        // stdDeviation 3 with an alpha slope of 20 keeps the ligature short.
        expect(container.querySelector('feGaussianBlur')?.getAttribute('stdDeviation')).toBe('3');
        expect(matrix?.getAttribute('values')?.trim().endsWith('20 -9')).toBe(true);
    });
});
```

- [ ] **Step 3: Run it and watch it fail**

```bash
npx vitest run resources/js/components/suivre/gooey-filter.test.tsx
```

Expected: FAIL — cannot resolve `./gooey-filter`.

- [ ] **Step 4: Implement it**

```tsx
/**
 * The one gooey filter in the application, mounted at the app shell so any
 * descendant can reference `url(#gooey)`.
 *
 * Two shapes inside a filtered group are blurred together and then had their
 * alpha re-contrasted, so overlapping edges fuse into one substance instead of
 * reading as two rectangles. Tuned deliberately soft: at stdDeviation 3 with an
 * alpha slope of 20 the ligature is short enough to be felt at the boundary and
 * invisible once the shapes come to rest. Turned up, the technique has a lot of
 * personality; this app wants almost none of it.
 *
 * The filter itself is static, so `prefers-reduced-motion` has nothing to
 * remove here — the travel it acts on is what gets removed, at the call site.
 */
export function GooeyFilter() {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            width="0"
            height="0"
            className="absolute"
        >
            <defs>
                <filter id="gooey">
                    <feGaussianBlur
                        in="SourceGraphic"
                        stdDeviation="3"
                        result="blurred"
                    />
                    <feColorMatrix
                        in="blurred"
                        type="matrix"
                        values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9"
                    />
                </filter>
            </defs>
        </svg>
    );
}
```

- [ ] **Step 5: Mount it in the app layout**

Import it and render `<GooeyFilter />` as the first child of the layout's outermost element. It occupies no space and is hidden from assistive technology, so it changes nothing visually until something references it.

- [ ] **Step 6: Run the test and the typecheck**

```bash
npx vitest run resources/js/components/suivre/gooey-filter.test.tsx
herd php artisan wayfinder:generate --with-form && npx tsc --noEmit
```

Expected: both PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/suivre/gooey-filter.tsx resources/js/components/suivre/gooey-filter.test.tsx resources/js/layouts/app-layout.tsx
git commit --no-gpg-sign -m "Mount the gooey filter primitive at the app shell"
```

---

### Task 5: D28, and the guideline sweep

The ticket is explicit that without this the next agent reads "no ornament" and reverts the lot. Per the documentation rules, the guideline sweep happens in the same change, not later.

**Files:**
- Modify: `docs/decisions/decision-log.md` (append)
- Modify: `.ai/guidelines/front-end-conventions.blade.php`
- Modify: `CLAUDE.md` and `AGENTS.md` (regenerated, never hand-edited)

- [ ] **Step 1: Append D28 to the decision log**

Match the house style exactly: `## D<n> — <sentence> (SUI-<n>)`, then `- **Decision (…):**` bullets, a `- **Why:**`, and a closing `- **Rules out:**`. Newest last; never rewrite an earlier entry.

```markdown
## D28 — Depth is material, not celebration: D20's "no ornament" settles what a surface is made of (SUI-58)

- **Decision (the test):** any new visual treatment is judged by one question — does it say something about **structure**, or something about the **user's performance**? Structure is in scope. Performance is D20's prohibition and stays prohibited, in full. A panel that sits above the page, a sheet with a translucent edge, a tinted field behind a group of controls: these are the same category of statement as a border, made better. None of them congratulate anybody.
- **Why this needed writing down:** D20 said "no ornament" and meant no *decoration that rewards*. It never settled what a surface is made of, because the question did not come up — the answer was implicitly "flat", and the result reads less like restraint than like an unstyled form. Left unrecorded, the next agent reads "no ornament" and reverts the material layer on sight.
- **Decision (three elevation steps, no more):** page, raised, floating. Each step is a **shadow token plus a background token** — never a shadow alone, which on a near-black surface reads as grime. Dark carries elevation mostly in the surface and light mostly in the shadow, so the two schemes are authored independently rather than one derived from the other.
- **Decision (glass only overlays):** translucency, `backdrop-filter: blur()` and a hairline border, used only where the element genuinely sits over content — the tab bar, sheets, popovers, a sticky header. Glass over nothing is a lie about depth and looks like one. Where `backdrop-filter` is unsupported the surface falls back to **opaque at the same elevation**, never to unreadable.
- **Decision (contrast is computed, not inspected):** glass is measured as **composited over the worst backdrop it can sit above**, not against its nominal fill. That worst case is body text, which is the luminance extreme in both schemes — a ramp swatch never reaches it. `MaterialLayerTest` proves it, and proves `app.css` declares exactly the values `Tests\Support\Material` names. This is D25's method applied to a second part of the system, for D25's reason.
- **Decision (no production enum):** the mirror lives in `tests/Support/Material.php` rather than `App\Enums`, because no server code consumes an elevation step. `ConditionHue` and `RampStep` are in PHP because a condition has a hue and a rating is bucketed server-side; an elevation enum would be a domain primitive with no domain.
- **Decision (gooey, tuned soft):** one `feGaussianBlur` into `feColorMatrix` alpha contrast, mounted once at the app shell, at `stdDeviation: 3` and an alpha slope of `20 -9`. Felt at the boundary, invisible at rest. If it reads as playful it is turned up too far. `prefers-reduced-motion` removes the travel the filter acts on; the filter is static and stays.
- **Rules out:** a fourth elevation step; a shadow applied without its surface; glass on anything that overlays nothing — including the calendar's day cells, whose ramp is a data encoding that translucency would corrupt; a bespoke shadow, blur radius or elevation colour at a call site; approving any of these by eye; reading D20's "no ornament" as a ban on material.
```

- [ ] **Step 2: Update the front-end guideline**

`.ai/guidelines/front-end-conventions.blade.php` already carries a "Colour ramps come from the server too" section explaining the `@theme` mechanics. Add a sibling section after it:

```markdown
## Depth comes from tokens

Elevation, glass, panel tint and the gooey filter are the material layer (D28). Every value is a
token in `app.css`; a component asks for `.elevation-raised` or `.glass` and never writes a shadow,
a blur radius or a surface colour of its own. A bespoke value at a call site is a review failure —
the point of a system is that the fifth screen costs less than the first.

Two things that will bite:

- **Elevation is a surface *and* a shadow.** `.elevation-raised` sets both, because a shadow alone
  on the dark scheme's near-black page reads as grime rather than as lift. There is no
  shadow-without-surface utility, deliberately.
- **Glass is only for things that overlay content** — the tab bar, sheets, popovers, a sticky
  header. Its contrast is proven against the *composite* over the worst backdrop it can sit above
  (`MaterialLayerTest`), not against its nominal fill, so changing `--glass-alpha` or either
  `--glass-surface` means re-running that test rather than looking at the result.
```

- [ ] **Step 3: Regenerate the woven guidelines**

```bash
herd php artisan boost:update
```

Never hand-edit the `<laravel-boost-guidelines>` block in `CLAUDE.md`/`AGENTS.md` — Boost rewrites it and drops stray edits.

- [ ] **Step 4: Verify the sweep caught everything**

Read the "Rules out" line of D28 and check each item against `docs/roadmap.md` and the spec/plan status banners. The interface-depth spec is Active and this plan enacts it, so no banner changes; confirm rather than assume.

```bash
rtk proxy grep -rn 'no ornament\|flat' docs/roadmap.md .ai/guidelines/
```

- [ ] **Step 5: Commit**

```bash
git add docs/decisions/decision-log.md .ai/guidelines/front-end-conventions.blade.php CLAUDE.md AGENTS.md
git commit --no-gpg-sign -m "Record D28: depth is material, not celebration"
```

---

### Task 6: Gate, and open the PR

**Files:** none — verification only.

- [ ] **Step 1: Run the whole gate**

```bash
herd composer check
```

Expected: Pint clean, PHPStan level 9 clean, `tsc --noEmit` clean, vitest and Pest green. Fix causes; never suppress, and never add a baseline.

- [ ] **Step 2: Prove nothing regressed visually**

Nothing consumes the tokens yet, so every screen must look **exactly** as it did before. That is the claim to check, and it is easy to check because it is a claim of no change.

```bash
npm run build
herd php artisan test tests/Browser/AppScreenshotsTest.php
```

Compare against the committed PNGs. Any diff at all means a token leaked into a rendered surface — find it.

- [ ] **Step 3: Push as the bot and open a draft PR**

`bin/worktree-create` sets this worktree's author to `autonomousjupiter`, but the `.envrc` that exports `GH_TOKEN` is loaded by direnv, which hooks interactive shells — a non-interactive agent shell may never trigger it and would silently push as the maintainer. Export it inline on every call:

```bash
GH_TOKEN="$(cat ~/.config/suivre/agent.token)" git push -u origin HEAD
```

Then invoke the **create-pr** skill, which resolves `SUI-58` from the branch name and fills `.github/PULL_REQUEST_TEMPLATE.md`. The pr-lint check fails any body lacking a Linear link, a checked "Types of changes" box, or a Deployment decision — so the template must be filled, not summarised. Base: `main`.

- [ ] **Step 4: Attach screenshots**

Invoke **capture-screenshots**. Because this ticket changes no rendered surface, the shots exist to demonstrate exactly that — say so in the PR body rather than implying a visual change.

---

## Self-review

**Spec coverage.** §4.1 elevation → Task 3. §4.2 glass, both hard constraints (worst-backdrop contrast, opaque fallback) → Task 3, Steps 2/5 and the `@supports` test. §4.3 panel tint → Task 3. §4.4 gooey → Task 4. §4.5 tokens-not-one-offs → Task 3 Step 5 plus the guideline in Task 5. §3's demand for a decision-log entry → Task 5. §7's contrast test → Task 3.

**Deliberately not here.** The spec's §7 day-page and reduced-motion tests belong to SUI-59; the "no bespoke shadow survives" sweep belongs to SUI-60. Both are named in those plans.

**Type consistency.** `Stylesheet::hex`/`raw` (Task 1) are the names Task 3 calls. `Wcag::composite($over, $under, $alpha)` (Task 2) is the signature Task 3 calls. `Material::surfaces()` returns token names without leading dashes and the test prepends `--`; that is consistent at both sites. `GooeyFilter` (Task 4) is the name SUI-60 imports.

**One judgement call recorded rather than hidden.** `--surface-page` and `--surface-raised` duplicate `--background` and `--card` in light. That is deliberate: a component should ask for an elevation, not for a background, and the two diverge in dark (`--surface-floating` is `#222324`, `--card` is `#1b1c1d`). Collapsing them would save four lines and lose the distinction the next screen needs.
