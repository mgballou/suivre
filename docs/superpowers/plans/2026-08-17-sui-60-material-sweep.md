# SUI-60 — Material Sweep Implementation Plan

- **Status:** Active
- **Ticket:** SUI-60, Linear project *Suivre v1*, milestone *V1 — Interface depth*
- **Spec:** `docs/superpowers/specs/2026-08-16-interface-depth-design.md` §6
- **Stack position:** top of the stack. Branches off SUI-59, which branches off SUI-58.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the material layer SUI-58 built and SUI-59 proved on one real screen to every remaining surface, replace every hand-written shadow and blur in authored code with a token, and build the tab bar's traveling gooey indicator.

**Architecture:** No screen changes structure. Calendar, insights, settings, onboarding and auth swap their ad-hoc borders and backgrounds for `.elevation-raised`, `.panel-tint` and `.glass`. The tab bar becomes real glass and gains an active indicator built from two pills at different transition speeds inside a `filter: url(#gooey)` group, so they fuse into one elastic shape while travelling and coincide exactly at rest. A guard test then makes the "no bespoke values" rule mechanical rather than a promise.

**Tech Stack:** Tailwind v4, CSS custom properties, SVG filters, React 19, TypeScript, Pest 4, vitest.

## Global Constraints

- **No structural change to any screen.** This ticket changes what surfaces are made of, not what is on them.
- **The calendar's day cells get no glass and no elevation.** They carry the petrol ramp, which is a data encoding; translucency over a value the user reads comparatively would corrupt the reading. **They must come out pixel-identical.**
- **Gooey only where it earns its place.** The tab bar indicator, and nowhere it merely decorates. Tuned soft.
- **`prefers-reduced-motion` removes travel** on every surface that gains motion. The static filter may stay.
- **No bespoke shadow, blur or surface colour** in authored code — see the scope note below for what "authored" means.
- **Every D20 commitment survives** (D28). No red, no streaks, no praise copy, no celebratory motion.
- **Toolchain:** `herd php`, `herd composer`.

## What "no bespoke value survives anywhere in `resources/js`" actually means

Taken literally the ticket's acceptance criterion would require rewriting `resources/js/components/ui/*`, which is shadcn/ui carried unmodified on purpose — the architecture rules say so explicitly, and editing it means every future `shadcn add` fights the local copy. `shadow-xs` and `shadow-sm` there are Tailwind scale utilities in vendored code, not bespoke values in ours.

**The rule applies to authored code:** `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/suivre/`, and the loose components directly under `resources/js/components/`. Task 4 encodes exactly that scope in a test, so the boundary is enforced rather than remembered.

There are exactly three offenders in authored code today. Confirm the list before you start — it was taken on `main` at `dcf9217` and the two branches below this one may have added more:

```bash
rtk proxy grep -rn -E 'shadow-|backdrop-blur|blur-\[|drop-shadow' \
  resources/js/pages resources/js/layouts resources/js/components/suivre \
  resources/js/components/*.tsx
```

| File | What is there | What it becomes |
|---|---|---|
| `components/appearance-tabs.tsx:35` | `bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100` | `elevation-raised` — and the raw `neutral` colours go with it; they are off-palette. |
| `components/two-factor-setup-modal.tsx:28` | `shadow-sm` on a bordered chip | `elevation-raised` |
| `components/suivre/tab-bar.tsx:18` | `bg-sidebar/95 backdrop-blur` | `glass` |

---

### Task 1: The tab bar becomes glass and gains a traveling indicator

The largest single change and the only one with new motion. Do it first, while attention is freshest.

**Files:**
- Modify: `resources/js/components/suivre/tab-bar.tsx`
- Test: `resources/js/components/suivre/tab-bar.test.tsx` (extend the existing file)

**Interfaces:**
- Consumes: `.glass` and `--tab-indicator` (SUI-58, Task 3); the filter id `gooey` mounted by `GooeyFilter` (SUI-58, Task 4).
- Produces: nothing other modules import. The indicator is internal to the tab bar.

#### How the indicator works

Three tabs, so each occupies a third of the bar. Two pills are positioned by the same CSS custom property and travel at different speeds:

- the **head** moves over `--dur-base` (220ms),
- the **tail** moves over `--dur-spatial` (380ms).

At rest they sit exactly on top of each other and the group looks like one pill. Mid-travel they separate, and the gooey filter fuses the gap into a short elastic ligature that snaps together on arrival. That is the whole effect: felt at the boundary, invisible at rest, which is what the spec asks for. Under `prefers-reduced-motion` both transitions are removed, so the pill simply appears on the new tab — no travel, and the filter, being static, has nothing left to do.

#### One finding that changes the markup

`--tab-indicator` is `#e2eeee` in light. The current active label colour, `--primary` (`#3f7d7b`), measures **3.99:1** against it and **fails WCAG AA**. So the active label becomes `text-foreground`: the pill is now what says "you are here", and the text does not need to say it twice. SUI-58's `MaterialLayerTest` already proves `--foreground` clears AA on `--tab-indicator` in both schemes, so this is the change that keeps that test honest rather than a preference.

- [ ] **Step 1: Read the existing test file first**

```bash
rtk proxy cat resources/js/components/suivre/tab-bar.test.tsx
npx vitest run resources/js/components/suivre/tab-bar.test.tsx
```

Expected: PASS. Extend this file; do not replace it. If an existing test asserts `text-primary` on the active tab, that assertion changes in Step 5 — update it, and say why in the commit message.

- [ ] **Step 2: Write the failing tests**

Append to the existing describe block, matching the file's existing idiom for stubbing `useCurrentUrl`.

```tsx
it('sits on the glass token rather than a hand-written blur', () => {
    const { container } = render(<TabBar />);
    const nav = container.querySelector('nav');

    expect(nav?.className).toContain('glass');
    expect(nav?.className).not.toMatch(/backdrop-blur|bg-sidebar\//);
});

it('points the indicator at the active tab by index', () => {
    const { container } = render(<TabBar />);
    const indicator = container.querySelector('[data-slot="tab-indicator"]');

    expect(indicator).not.toBeNull();
    expect(indicator?.getAttribute('style')).toContain('--tab-index');
    expect(indicator?.getAttribute('style')).toContain('--tab-count: 3');
});

it('fuses its two pills through the shared gooey filter', () => {
    const { container } = render(<TabBar />);
    const indicator = container.querySelector('[data-slot="tab-indicator"]');

    expect(indicator?.getAttribute('style')).toContain('url(#gooey)');
    expect(indicator?.querySelectorAll('span')).toHaveLength(2);
});

it('removes the travel under reduced motion and keeps the pill', () => {
    const { container } = render(<TabBar />);
    const pills = container.querySelectorAll('[data-slot="tab-indicator"] span');

    for (const pill of pills) {
        expect(pill.className).toContain('motion-reduce:transition-none');
    }
});

it('hides the indicator from assistive technology, which reads aria-current instead', () => {
    const { container } = render(<TabBar />);

    expect(container.querySelector('[data-slot="tab-indicator"]')?.getAttribute('aria-hidden')).toBe('true');
});
```

- [ ] **Step 3: Run them and watch them fail**

```bash
npx vitest run resources/js/components/suivre/tab-bar.test.tsx
```

Expected: FAIL on the four new assertions.

- [ ] **Step 4: Rewrite the nav element and add the indicator**

Replace the whole component body. Read the current file first so you keep `useCurrentUrl`, `mainNavItems`, the `env(safe-area-inset-bottom)` padding and the `min-h-11 min-w-11` touch targets exactly as they are — none of those are in scope.

```tsx
import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { mainNavItems } from '@/lib/nav';
import { cn } from '@/lib/utils';

/**
 * Mobile bottom navigation. The desktop rail (AppSidebar) presents the same
 * `mainNavItems`.
 *
 * The bar is real glass (D28): it genuinely overlays the page it sits above, so
 * translucency here says something true about depth. Its contrast is proven
 * against the composite over the worst backdrop it can sit over, in
 * MaterialLayerTest — not against the token's nominal fill.
 *
 * The active indicator is two pills at different speeds inside one gooey group.
 * At rest they coincide and read as a single shape; mid-travel they separate
 * and the filter fuses the gap into a short ligature. Under reduced motion both
 * transitions are removed, so the pill appears on the new tab without
 * travelling — the filter is static and has nothing left to act on.
 *
 * The label is `text-foreground`, not `text-primary`: petrol measures 3.99:1 on
 * the indicator and fails AA. The pill is what says "you are here".
 */
export function TabBar({ className }: { className?: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const activeIndex = mainNavItems.findIndex((item) =>
        isCurrentOrParentUrl(item.match ?? item.href),
    );

    return (
        <nav
            aria-label="Primary"
            className={cn(
                'glass fixed inset-x-0 bottom-0 z-50 border-t',
                className,
            )}
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <div className="relative mx-auto max-w-lg">
                {activeIndex >= 0 && (
                    <div
                        aria-hidden
                        data-slot="tab-indicator"
                        className="pointer-events-none absolute inset-y-1 left-0 w-full"
                        style={{
                            '--tab-index': activeIndex,
                            '--tab-count': 3,
                            filter: 'url(#gooey)',
                        } as React.CSSProperties}
                    >
                        <span
                            className={cn(
                                'absolute inset-y-0 rounded-md bg-[var(--tab-indicator)]',
                                'transition-[left] duration-[var(--dur-spatial)] ease-quiet',
                                'motion-reduce:transition-none',
                            )}
                            style={{
                                width: 'calc(100% / var(--tab-count) - 0.5rem)',
                                left: 'calc(var(--tab-index) * (100% / var(--tab-count)) + 0.25rem)',
                            }}
                        />
                        <span
                            className={cn(
                                'absolute inset-y-0 rounded-md bg-[var(--tab-indicator)]',
                                'transition-[left] duration-[var(--dur-base)] ease-quiet',
                                'motion-reduce:transition-none',
                            )}
                            style={{
                                width: 'calc(100% / var(--tab-count) - 0.5rem)',
                                left: 'calc(var(--tab-index) * (100% / var(--tab-count)) + 0.25rem)',
                            }}
                        />
                    </div>
                )}

                <ul className="relative flex items-stretch justify-around">
                    {mainNavItems.map((item) => {
                        const active = isCurrentOrParentUrl(item.match ?? item.href);
                        const Icon = item.icon;

                        return (
                            <li key={item.title} className="flex-1">
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex min-h-11 min-w-11 flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium',
                                        'transition-colors duration-[var(--dur-micro)] ease-quiet',
                                        active
                                            ? 'text-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {Icon && <Icon className="size-5" aria-hidden />}
                                    <span>{item.title}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </nav>
    );
}
```

`bg-[var(--tab-indicator)]` is an arbitrary-value class pointing at a token, which is what the rule is for — it is not a bespoke colour. Task 4's guard test only forbids raw shadow and blur values, so this stays clean.

- [ ] **Step 5: Run the tests and watch them pass**

```bash
npx vitest run resources/js/components/suivre/tab-bar.test.tsx
npx tsc --noEmit
```

Expected: PASS. `--tab-index` and `--tab-count` in a `style` object need the `as React.CSSProperties` cast shown above; TypeScript rejects custom properties on the plain type. That cast is the one place a comment earns its keep — say that custom properties are not in `CSSProperties`.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/suivre/tab-bar.tsx resources/js/components/suivre/tab-bar.test.tsx
git commit --no-gpg-sign -m "Make the tab bar glass and give it a gooey traveling indicator"
```

---

### Task 2: Replace the two remaining bespoke shadows

Small, mechanical, and it clears the way for the guard test in Task 4.

**Files:**
- Modify: `resources/js/components/appearance-tabs.tsx`
- Modify: `resources/js/components/two-factor-setup-modal.tsx`

- [ ] **Step 1: Fix the appearance tabs**

Line 35 currently reads `'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'` on the selected tab. `bg-white` and `dark:bg-neutral-700` are raw Tailwind palette colours that were never part of this design system — that is why the selected tab looks slightly wrong in dark mode. Replace the whole selected-state string with:

```
'elevation-raised text-foreground'
```

Leave the unselected state exactly as it is.

- [ ] **Step 2: Fix the two-factor chip**

Line 28: `"mb-3 rounded-full border border-border bg-card p-0.5 shadow-sm"`. `bg-card` plus `shadow-sm` is precisely what `.elevation-raised` means, so:

```
"mb-3 rounded-full border border-border p-0.5 elevation-raised"
```

- [ ] **Step 3: Verify and commit**

```bash
npx tsc --noEmit && npx vitest run
git add resources/js/components/appearance-tabs.tsx resources/js/components/two-factor-setup-modal.tsx
git commit --no-gpg-sign -m "Replace the last two hand-written shadows with elevation tokens"
```

---

### Task 3: Sweep the remaining screens

Elevation and panel tint, applied where a group of controls or a block of content currently relies on a bare border or nothing at all. **No screen changes structure.** If you find yourself moving an element, stop — that is out of scope.

**Files:**
- Modify: `resources/js/pages/calendar.tsx`, `insights.tsx`
- Modify: `resources/js/pages/settings/appearance.tsx`, `conditions.tsx`, `profile.tsx`, `security.tsx`
- Modify: `resources/js/pages/onboarding/conditions.tsx`
- Modify: `resources/js/layouts/auth/auth-card-layout.tsx`, `auth-simple-layout.tsx`, `auth-split-layout.tsx`
- Modify: `resources/js/layouts/settings/layout.tsx`

- [ ] **Step 1: Read every file in the list before editing any of them**

You are looking for two shapes:

- a block of content sitting directly on the page with a border round it → `.elevation-raised` (and drop the border only if it now reads as doubled; keep it if it does not);
- a group of form controls with a heading, sitting on nothing → wrap in `.panel-tint rounded-lg p-4`.

- [ ] **Step 2: The calendar — the one screen with a hard exclusion**

`resources/js/pages/calendar.tsx` may take elevation on the month container, the navigation header, or a surrounding card. **The day cells themselves take nothing.** Do not touch `day-cell.tsx` or `month-grid.tsx` at all — not their classes, not their structure. Their output must be byte-identical.

Verify that claim rather than asserting it:

```bash
git diff --stat -- resources/js/components/suivre/day-cell.tsx resources/js/components/suivre/month-grid.tsx
```

Expected: empty output. If either file appears, revert it.

- [ ] **Step 3: The insights page**

`insights.tsx` renders cards already. Give them `.elevation-raised` and remove any bare `border` that then reads as doubled. The `exposure-timeline`, `trend-chart` and `suspect-list` components keep their own internals — the ramp and the series colours are data encodings and are not material.

- [ ] **Step 4: Settings, onboarding and auth**

`layouts/settings/layout.tsx` wraps every settings screen; putting the tint there is cheaper and more consistent than repeating it on four pages — prefer that if the structure allows. The auth layouts each present a single centred form: `auth-card-layout` already has a card, so it takes `.elevation-raised`; the other two take it on whatever container currently holds the form.

- [ ] **Step 5: Build, typecheck, test**

```bash
herd php artisan wayfinder:generate --with-form && npx tsc --noEmit
npm run build
npx vitest run
herd php artisan test --compact --filter='day-cell|month-grid|intensity-picker'
```

Expected: all PASS, and the three ramp suites unchanged.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages resources/js/layouts
git commit --no-gpg-sign -m "Inherit elevation and panel tint across the remaining surfaces"
```

---

### Task 4: The guard test

Makes the acceptance criterion mechanical. Without it the rule survives exactly as long as the next agent remembers it.

**Files:**
- Create: `tests/Feature/Design/MaterialSweepTest.php`

**Interfaces:**
- Consumes: nothing. It reads the filesystem.

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * The material layer is only a system while every call site uses it (D28). A
 * hand-written shadow or blur radius is what turns it back into a pile of
 * one-offs, so the rule is enforced here rather than remembered.
 *
 * Scope is authored code. `components/ui` is shadcn/ui carried unmodified on
 * purpose — its `shadow-xs` is a vendored Tailwind utility, and editing it
 * would put every future `shadcn add` in conflict with the local copy.
 */
function authoredComponents(): Finder
{
    return Finder::create()
        ->files()
        ->name('*.tsx')
        ->notName('*.test.tsx')
        ->in([
            resource_path('js/pages'),
            resource_path('js/layouts'),
            resource_path('js/components/suivre'),
        ])
        ->append(
            Finder::create()
                ->files()
                ->name('*.tsx')
                ->notName('*.test.tsx')
                ->depth(0)
                ->in(resource_path('js/components')),
        );
}

it('writes no shadow of its own outside the elevation tokens', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/\b(shadow-(?!none)|drop-shadow-)/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use .elevation-raised or .elevation-floating instead of a shadow utility.');
});

it('writes no blur of its own outside the glass token', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/\b(backdrop-blur|blur-\[|blur-(sm|md|lg|xl))\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use .glass instead of a blur utility.');
});

it('keeps the raw palette out of authored code, where the design tokens belong', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/\b(bg|text|border)-(neutral|gray|slate|zinc|stone|red|green|blue)-\d{2,3}\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use a semantic token — the palette is not the design system.');
});
```

- [ ] **Step 2: Run it**

```bash
herd php artisan test --compact tests/Feature/Design/MaterialSweepTest.php
```

Expected: PASS. If it fails, it has found a real offender Tasks 1–3 missed — fix the component, never the pattern. If the third test flags something in a chart or an icon where a literal colour is genuinely required, that is a real exception: narrow the regex with a comment saying which file forced it and why, rather than deleting the test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Design/MaterialSweepTest.php
git commit --no-gpg-sign -m "Enforce the no-bespoke-values rule in authored components"
```

---

### Task 5: Verify the motion contract, gate, and open the PR

- [ ] **Step 1: Run the whole gate**

```bash
herd composer check
```

- [ ] **Step 2: Confirm the day cells are untouched**

```bash
git diff --stat "$(git merge-base HEAD origin/main)"...HEAD -- resources/js/components/suivre/day-cell.tsx resources/js/components/suivre/month-grid.tsx
```

Expected: empty. This is an explicit acceptance criterion, so evidence beats assertion — paste the output into the PR body.

- [ ] **Step 3: Look at every swept surface, at three widths and both schemes**

Calendar, insights, all four settings screens, onboarding and login, at 390px, 768px and 1280px, in light and dark. Use **capture-screenshots**, and remember its ordering rule: chain `->inLightMode()` / `->inDarkMode()` on the *pending* page, before `->on()` resolves it.

What to check, specifically:

- the tab bar's glass reads as translucent over content and stays legible where the darkest (light scheme) or lightest (dark scheme) content scrolls under it;
- the indicator sits centred on the active tab and its gooey edges look like a soft-cornered pill, not a blob — if it reads as playful, lower `stdDeviation` in `gooey-filter.tsx` and re-run its test;
- day cells are visually identical to the SUI-59 shots;
- no screen has a doubled edge where an elevation surface kept a border it no longer needs;
- panel tint is faint. If you can name its colour without looking closely, it is too strong.

- [ ] **Step 4: Verify reduced motion by hand**

Screenshots cannot show the absence of travel. Capture the tab bar mid-transition with reduced motion forced, or assert it in the browser test — either is fine, but do one of them and say which in the PR.

```php
visit('/calendar')->on()->mobile()->script('matchMedia("(prefers-reduced-motion: reduce)").matches');
```

- [ ] **Step 5: Push as the bot and open the PR based on the SUI-59 branch**

```bash
GH_TOKEN="$(cat ~/.config/suivre/agent.token)" git push -u origin HEAD
```

Invoke **create-pr**. **The base is the SUI-59 branch**, not `main` and not SUI-58. Note in the body that it is the top of a three-PR stack, and record the scope decision about `components/ui` explicitly — a reviewer reading the ticket's "anywhere in `resources/js`" will otherwise think it was missed.

- [ ] **Step 6: Attach the screenshots** via **capture-screenshots**.

---

## Self-review

**Spec coverage.** §6's "calendar, insights, settings, onboarding and auth inherit elevation, tint and glass" → Task 3. "Any bespoke shadow or border found along the way is replaced by a token" → Tasks 1–2, enforced by Task 4. "Gooey applied only where it earns its place — the tab bar's active indicator as it travels" → Task 1. The chips-merging half of that sentence belongs to SUI-55's meal composer and is out of this ticket's scope; SUI-55 will consume the same `GooeyFilter`. §6's day-cell exclusion → Task 3 Step 2 and Task 5 Step 2, checked twice. §7's reduced-motion verification → Task 5 Step 4.

**One acceptance criterion knowingly reinterpreted.** "No bespoke shadow or blur value survives anywhere in `resources/js`" is scoped to authored code, for the reason given at the top and encoded in Task 4's `Finder`. It is called out in the PR body rather than quietly narrowed.

**Type consistency.** `--tab-index` / `--tab-count` are set in the same `style` object the tests assert on. `data-slot="tab-indicator"` is the single selector all five tab-bar tests use. `.glass`, `.elevation-raised`, `.panel-tint` and `--tab-indicator` are the exact names SUI-58 Task 3 produces. `GooeyFilter`'s id is `gooey`, matching `filter: 'url(#gooey)'` here.

**Known risk.** `filter: url(#gooey)` creates a containing block. It is applied to a child of the glass nav, not to the nav itself — putting it on the nav would break `backdrop-filter` on the same element. If the indicator ever moves up a level, that breaks, and the symptom is glass that stops blurring rather than an error.
