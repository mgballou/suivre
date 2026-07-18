---
name: capture-screenshots
description: "Use when asked to capture, take, or grab screenshots of app pages — Filament admin pages, Inertia/React pages, modals — including dark-mode, mobile/viewport, and browser variants; when deciding which changed screens warrant shots before opening a PR; or when finalizing a PR that touches UI (offer PR screenshots even if unasked). Generates PNGs via a throwaway Pest browser test that seeds factory data, authenticates with actingAs, visits the target pages, and calls ->screenshot(). Takes precedence over running the live app when the deliverable is a screenshot."
---

# Capture Screenshots

## Overview

Generate screenshots with a throwaway, request-scoped **Pest browser test** that seeds its own factory data,
authenticates via `actingAs`, visits the target Filament/Inertia pages, and calls `->screenshot()`. The
flagship use is PR screenshots — embeddable via SHA-pinned git-history URLs (§4) — but the same flow serves
any "get me a shot of page X" request.

The screenshots directory is a **live snapshot, not an archive**: on a re-run, capture each surface to the
filename it already uses, so the directory always represents the most recent version of every surface.

Run everything through **Herd**: `herd php artisan test tests/Browser/…`. Tests run on the `suivre_test`
Postgres DB.

### Prerequisites (one-time — the repo has no browser tests yet)

`visit()` needs the browser plugin + Playwright, which are **not installed by default**. If a run reports
"Using the visit() function requires the Pest Plugin Browser to be installed", run once:

```bash
herd composer require pestphp/pest-plugin-browser:^4.0 --dev
npm install -D playwright && npx playwright install chromium
```

Pest only binds `TestCase` + `RefreshDatabase` to `->in('Feature')`, so add `Browser` to that binding in
`tests/Pest.php` (`->in('Feature', 'Browser')`) or the browser test boots with no app/DB. This is the first
`tests/Browser/` test — creating it is expected.

Core principles:
- **Seed factory data, never the live/dev DB.** `actingAs` means you never need a password, and you never
  post real data into a PR.
- **The test is disposable.** Default to deleting it when done; keep it only if the author wants it as smoke
  coverage (CI would then run it every push).
- **State concretely; verify, don't re-discover.** Suivre is **single-user MVP** — no Filament tenancy, no
  roles/permissions. Confirm the page rendered for the seeded user (§2) rather than re-deriving the access
  model each run.

## 0. Resolve context first

Don't ask for the branch/ticket/PR — read them:

```bash
git rev-parse --abbrev-ref HEAD                               # matthewbuiltthat/sui-<n>-<slug>
gh pr view --json number,title,baseRefName,url 2>/dev/null   # PR + base, if one exists
```

- **Subdir label** for `Screenshots/<label>/`: the `sui-<n>` from the branch (or the PR number `pr-<n>`).
- **Diff base:** the PR's `baseRefName` if a PR exists, else `main`.
- If the user named the pages, skip §1 — the surfaces come from the ask.

## 1. Decide what to screenshot

Derive surfaces from the diff (don't rely on memory; swap `main` for the resolved base):

```bash
git diff --name-only main...HEAD -- 'app/Filament/**' 'resources/js/pages/**' 'resources/views/**/*.blade.php'
```

| Changed file | Screenshot target |
|---|---|
| Filament Page class | that page's `::getUrl()` |
| Resource Table / RelationManager / Widget / Schema | the page that hosts it (the resource's List/View/Edit page) |
| Inertia/React page (`resources/js/pages/**`) | its route — note public vs. auth; detail pages bind by id and need a seeded record |
| Filament Action with a `->schema()`, or a front-end modal | the modal it opens (often the actual changed surface — see Modals) |

Pick the few pages that demonstrate the change. Classify each as **NEW** or **CHANGED** — a changed
surface heading into a PR may warrant a before/after pair (§ Before shots); a new surface never does.

## 2. Write the scoped browser test

Create `tests/Browser/<Feature>ScreenshotsTest.php`. Build Filament URLs with `::getUrl()` — **Suivre has no
tenancy**, so no `tenant:` argument (simpler than multi-tenant apps).

### Authenticate past the access gate

Suivre is single-user: `User` implements `FilamentUser` with `canAccessPanel(): true` (D16), so any seeded
user reaches `/admin`. There are **no roles to seed** — just `actingAs` a user.

Use a **stable identity** so avatar/initials and any user-derived chrome render identically across shots and
re-runs (not fresh random data each factory call):

```php
beforeEach(function (): void {
    $this->user = User::factory()->create([
        'name' => 'Sam Rivard',
        'email' => 'sam@suivre.test',
    ]);
    $this->actingAs($this->user);
});

it('captures the meals index', function (): void {
    Meal::factory()->count(3)->for($this->user)->createQuietly();

    visit(ListMeals::getUrl())
        ->assertSee('Meals')                              // proves it rendered for this user (auto-waits)
        ->screenshot(filename: 'sui-XX/01-meals-index');  // subdir per ticket
});
```

Keep **domain data consistent across a set** of shots (same meal names, dates, intensities) via a shared
seeding helper — each `it()` gets its own DB (`RefreshDatabase`), so without one every shot invents
unrelated data. The `->assertSee()` on real gated content (never the page title alone) doubles as proof the
seeded user got in; a sudden failure there means the access model drifted — fix the seeding and update this
section.

Run: `herd php artisan test tests/Browser/<Feature>ScreenshotsTest.php`.

### Variants: theme + viewport + browser

Capture a variant only when the diff exercises that axis — never the full cross-product.

- **Theme (dark/light)** — Filament admin only (the front-end has no dark mode by default). Chain
  `->inLightMode()` / `->inDarkMode()`.
- **Viewport** — admin and front-end (both are responsive). `->on()->mobile()` / `->on()->desktop()` /
  `->resize($w, $h)`. Set the viewport *before* asserting and shooting — layouts re-flow on resize.
- **Browser (Chrome default / firefox / safari=WebKit)** — only for engine-specific fixes. Selection is
  **per-run**, not per-visit: `--browser chrome|firefox|safari` on the test command. Give each browser
  variant its own `it()` (browser word in the name, suffix in the filename), and pair `--filter` with
  `--browser`, or a bare run executes the safari/firefox blocks in Chrome and clobbers their PNGs. Install
  engines once: `npx playwright install firefox webkit`.

Suffix filenames `-light`/`-dark`, `-mobile`/`-desktop`, `-firefox`/`-safari` (no suffix = Chrome).

### Front-end (Inertia/React) pages

Public/buyer pages render through the **Vite build**, not Filament's published assets:

- **Build assets first** — `npm run build` (or have `npm run dev` running). `app.blade.php` code-splits per
  page, so a page missing from the Vite manifest 500s. `public/build` is gitignored.
- **Assert no JS errors** — React can fail client-side with no HTTP error. `->assertNoSmoke()` surfaces JS
  errors *and* waits for render.

```php
it('captures the dashboard', function (): void {
    visit(route('dashboard'))
        ->assertNoSmoke()
        ->assertSee('Today')
        ->screenshot(filename: 'sui-XX/05-dashboard');
});
```

### Modals & interactive states

A modal is often the changed surface. **Filament action modals:** click the trigger by label, assert a field
inside (opens it + auto-waits through the Livewire round-trip), let it animate, shoot:

```php
visit(ListMeals::getUrl())
    ->click('Log meal')          // the Action's ->label()
    ->assertSee('Eaten at')       // a field inside the modal
    ->wait(1)                     // open animation
    ->screenshot(filename: 'sui-XX/log-meal-modal');
```

The trigger only renders if its `->visible()`/policy gate passes — seed whatever that gate checks.
**Front-end modals:** if a DOM trigger opens it, `->click()` and wait for its content; if it opens only from
client-side state, capture it manually or add a temporary dev-only prop hook, `npm run build`, shoot, then
revert and rebuild clean.

## 3. Captions and focus

### Captioned shots

The "Shows" line may claim only what is **visible in the pixels** — if a claim matters (e.g. a filter behind
a closed dropdown), capture the state that shows it or drop the claim. Bake a three-line banner into every
shot: line 1 the axis combo (`browser · theme · viewport`), line 2 the page path (read from `location`), line
3 the "Shows" text. Define the helper once in `beforeEach`. Keep the nowdoc + `sprintf` form — interpolating
`json_encode(...)` into a quoted string breaks on the JS's own quotes:

```php
$this->caption = fn (string $settings, string $shows): string => sprintf(
    <<<'JS_CAPTION'
    (() => {
        const banner = document.createElement('div');
        banner.style.cssText = 'background:#1e3a8a;color:#fff;padding:8px 16px;font:13px/1.6 sans-serif;text-align:center;position:relative;z-index:10000';
        const settings = document.createElement('div');
        settings.style.fontWeight = '700';
        settings.textContent = %s;
        const url = document.createElement('div');
        url.style.opacity = '0.8';
        url.textContent = location.pathname + location.search + location.hash;
        const shows = document.createElement('div');
        shows.style.cssText = 'text-wrap:balance';
        shows.textContent = %s;
        banner.append(settings, url, shows);
        document.body.prepend(banner);
    })()
    JS_CAPTION,
    json_encode($settings),
    json_encode($shows),
);
```

`->script()` returns the script's value (`undefined` → `null`), breaking the chain — hold the page in a
variable, inject the caption **after** assertions/waits and immediately **before** the shot:

```php
$page = visit($url)->assertSee('Meals')->wait(1);
$page->script(($this->caption)('Chrome · Light · Desktop', 'Meals index, 3 seeded meals'));
$page->screenshot(filename: 'sui-XX/01-meals-index');
```

### Focus: full page, spotlight, or crop

The question every shot answers: can the reviewer, at GitHub's inline width, read the thing the shot exists
to show?

| Mode | Use when |
|---|---|
| **Full page** (default) | the change is the page, spans regions, or the page is short |
| **Spotlight** (dim the page, pop one region) | long page, one region, placement matters |
| **Crop** (`->screenshotElement($sel, $file)`) | small self-explanatory target; detail legibility is the point |

A crop loses placement context — pair it with a full-page shot when the page is unfamiliar or the target's
meaning depends on off-crop controls. Cropping via `->screenshotElement()` clips to the element's live box —
never narrow the viewport to crop (that flips responsive breakpoints). Reusable **spotlight** and
**cropWithCaption** helpers (dim overlay by page luminance; caption band + background ring + invisible frame)
are worth defining in `beforeEach` when a set needs them; keep their find-expression targeting
(`closest('section.fi-section')` for Filament forms) and shoot `'#pest-crop-frame'` for crops.

### Before shots (changed surfaces) — capture from a worktree, never revert

The "before" renders base code, but the working tree must not move — use a disposable worktree:

```bash
git worktree remove --force /tmp/before-shots 2>/dev/null || true
BASE_SHA=$(git merge-base HEAD origin/main)
git worktree add /tmp/before-shots "$BASE_SHA"
cp .env /tmp/before-shots/.env                      # APP_KEY etc.
ln -s "$PWD/node_modules" /tmp/before-shots/node_modules
(cd /tmp/before-shots && herd composer install --quiet && npm run build)
# write a <Feature>BeforeShotsTest.php asserting the OLD state, filenames suffixed -before, then:
(cd /tmp/before-shots && herd php artisan test tests/Browser/<Feature>BeforeShotsTest.php)
cp /tmp/before-shots/tests/Browser/Screenshots/<label>/*-before.png tests/Browser/Screenshots/<label>/
git worktree remove --force /tmp/before-shots
```

`composer install` is mandatory (never symlink `vendor` — the autoloader would load the after-code);
`node_modules` may be a symlink; `npm run build` is required (gitignored `public/build`). Label a before/after
**pair** symmetrically: `-before`/`-after` filename suffix, `· BEFORE`/`· AFTER` in the caption, red banner
(`#7f1d1d`) for before vs the default blue. A standalone shot is not an "after" — leave it unsuffixed/blue.
Cleanup is path-scoped: only ever create/remove `/tmp/before-shots`; never touch other `git worktree list`
entries (Suivre uses worktrees for real work per **starting-work**).

## 4. Hand off — show the table, then ask

Render a results table after **every** capture run (including each refinement iteration), then ask what to do
with the shots (AskUserQuestion) — but only the first time / when the destination isn't settled. Never offer
a "show the table" option; it's already shown.

Link the subdir as a `file://` markdown link (the only clickable form in this terminal), then one row per
shot — filename, Focus mode, "Shows":

```
[tests/Browser/Screenshots/sui-XX/](file:///Users/…/suivre/tests/Browser/Screenshots/sui-XX/)

| File | Focus | Shows |
|---|---|---|
| `01-meals-index.png` | Full page | Meals index with 3 seeded meals |
```

Destinations: **Embed in the PR** (recommend when a PR exists), **Open the folder** (macOS `open`), or
**Nothing further**. Delete the throwaway test when done unless the author wants it as smoke coverage.

### Embedding in a PR (SHA-pinned git-history)

GitHub has no API for the drag-and-drop upload, but images embed by parking them in the PR's git history —
the add-commit stays reachable through `refs/pull/<N>/head` forever even after the removal commit:

```bash
mkdir -p docs/screenshots/<label> && cp tests/Browser/Screenshots/<label>/*.png docs/screenshots/<label>/
git add docs/screenshots/<label> && git commit -m "chore: add pr screenshots for sha-pinned embedding"
SHOT_SHA=$(git rev-parse HEAD)
git rm -rq docs/screenshots/<label> && git commit -m "chore: remove pr screenshots now that embed urls are pinned"
```

(`docs/screenshots/` because `tests/Browser/Screenshots/` is gitignored — do not un-ignore it.) Push, then
embed with the **`github.com/<owner>/<repo>/raw/<SHOT_SHA>/<path>`** form (renders for authed viewers;
`raw.githubusercontent.com` shows a broken image in PR bodies):

```md
![Meals index](https://github.com/mgballou/suivre/raw/<SHOT_SHA>/docs/screenshots/<label>/01-meals-index.png)
```

**Verify the pin, not the URL** — the `…/raw/<sha>/…` link 404s to anonymous `curl`/`gh api` (it's served by
github.com's web frontend with the viewer's session). Confirm the commit + its files reached GitHub instead:

```bash
gh api repos/mgballou/suivre/commits/<SHOT_SHA> -q '.files[].filename'
```

## Gotchas

- **Build page URLs inside the `it()` body, never in `beforeEach`.** The browser plugin swaps `app.url` to
  its local server *after* `beforeEach`, so a `::getUrl()`/`route()` built there bakes in the Herd domain —
  the browser then drives the live site (wrong DB, no session) and every shot is its login page.
- **Create the screenshot subdir first** — `->screenshot('sui-XX/…')` does not `mkdir`; run
  `mkdir -p tests/Browser/Screenshots/sui-XX` once.
- **Re-runs refresh by surface — overwrite, don't accumulate.** Reuse an existing PNG's exact filename for
  the same surface; pick a new name only for a surface with no existing shot.
- **A failing browser test auto-saves a screenshot** under `Screenshots/<TestName>/` — delete those.
- **Content that renders late shows blank.** Filament widgets/relation managers lazy-load; React mounts
  client-side. `->assertSee('<post-load text>')` auto-waits; add `->wait(1)` for safety.
- **View every PNG** (open it / read the file) to confirm it's styled, authenticated, and populated. A blank
  shot means a missing render wait or stale/un-built Vite assets (`npm run build`).
- **`->click('Text')` only finds visible text** — icon-only triggers (row `…` menus, header filter icons)
  time out. Pass a CSS selector: `->click('[aria-label="…"]')`.
- **No `Event::fake()` in screenshot tests** — an argument-less `Event::fake()` swallows the event Livewire
  uses to inject its assets, blanking every Filament page (text still asserts, but nothing renders). Seed via
  factories and dispatch nothing.
