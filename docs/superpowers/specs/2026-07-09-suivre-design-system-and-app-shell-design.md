# Suivre — Design System & App Shell

- **Date:** 2026-07-09
- **Status:** Approved (design)
- **Tickets:** parent SUI-5, split into two sub-issues (Ticket A, Ticket B below)
- **Supersedes:** D7 (Livewire/Volt bespoke PWA), the frontend half of D13, the UI layer of D16
- **Amends:** D8 (one charting standard)

---

## 1. Context

Suivre's user-facing app does not exist yet. `resources/views/**` is the unmodified Laravel
Livewire starter kit: a placeholder dashboard, stock sidebar chrome linking to the Laravel docs,
and 1,623 lines of Flux-built auth and settings views. SUI-5 is the first product UI ticket.

Two questions were settled during brainstorming:

1. **The stack.** Livewire 4 + Flux is replaced by **Inertia + React 19 + TypeScript + shadcn/ui**
   for the user-facing app. Filament stays on `/admin`, unchanged, and keeps `livewire/livewire`
   as its own dependency.
2. **The design.** A coherent visual and behavioural philosophy — recorded below — that the
   calendar (SUI-6), day view (SUI-7) and insights (E4) all inherit.

The stack change was made at the cheapest possible moment: zero user-facing screens exist, and the
official React starter kit ships passkeys, 2FA, email verification and settings against the same
Fortify backend already configured here. The migration is *delete and adopt*, not *port*.

**The stack change was not made because Flux has a paid tier.** Flux free covers roughly forty
components; the paywall bites on two the roadmap needs (autocomplete, tag input), both in E3, both
deferrable. The switch was made on developer fluency, and the fact that shadcn happens to include
combobox, slider, tabs, sidebar and date-picker for free is a dividend, not a rationale.

The domain layer — Actions, enums, events, policies, strict Eloquent, PHPStan level 9 — is
untouched by any of this. `.ai/spatie-data`'s rule that Actions return `readonly` DTOs rather than
arrays turns out to be exactly what Inertia props want.

---

## 2. Design philosophy — *quiet instrument*

Suivre is a measuring device that happens to be kind. It is not a friend, it does not cheer, and it
never softens the data to make you feel better. What makes it calm is not rounded corners and a
warm typeface — it is that the instrument refuses to judge you.

Calm is therefore relocated from ornament to behaviour:

- **Calm lives in the semantics.** The intensity ramp is one hue deepening. There is no red. A
  severe month renders as depth, not damage — a topography, not a report card.
- **Calm lives in the copy.** Per D11: "suggestive, not proof", sample sizes always visible. The
  app says *"12 of your 19 dairy days preceded a flare"* and stops talking. It never says *"dairy
  is your trigger"*, and it never says *"great job logging today!"*
- **Calm lives in what is absent.** No streaks. No completion rings. No guilt notifications.
  Missing a day is data, not failure; empty cells are quiet, not accusatory. **This is the most
  important rule in the system.** Every tracker that broke it became a source of anxiety for
  exactly the people it was built for.
- **Calm lives in restraint of motion.** See §3.
- **Credibility lives in the surface.** Inter with tabular figures, radius 8, a tight grid, petrol.
  The visual language of a well-made readout.

### Failure modes of this direction

**Density must not eat the tap target.** This is a phone app whose core interaction is two taps.
Every interactive element gets a **44×44px minimum hit area** (`min-h-11 min-w-11`) regardless of
how small it renders. Enforced by review habit, not tooling.

**Cold is the risk; copy and motion are the antidotes.** With ornament stripped out, writing and
movement carry the entire emotional load. Tone is specified alongside colour and is not decoration.

---

## 3. Motion

**Motion explains, and it breathes. It never celebrates.**

Warmth in motion comes from easing and timing, not displacement. Bounce reads as toy. A long
decelerating tail reads as considered — an object with friction, settling.

**Curves.** The default `ease-in-out` is banned. Two tokens, and only two:

- `--ease-quiet: cubic-bezier(0.32, 0.72, 0, 1)` — entrances and spatial moves. Fast commit, long
  soft settle, zero overshoot.
- `--ease-exit: cubic-bezier(0.4, 0, 1, 1)` — exits. Quicker and less precious; nobody watches a
  thing leave.

**Durations:**

| Token | Value | Used for |
|---|---|---|
| `--dur-micro` | 120ms | Tap feedback, hover, focus rings |
| `--dur-base` | 220ms | Cross-fades, tab changes, toasts |
| `--dur-spatial` | 380ms | Day cell → day view, month pans |
| `--dur-arrival` | 600ms | A logged value's colour easing into the calendar |

**Panning carries meaning.** Next month slides left, previous slides right — the motion *is* the
direction of time. Tapping a day lifts the day view up out of that cell; back lowers it home.
Nothing hard-swaps; everything cross-fades.

**The one moment of whimsy.** When a check-in is logged, the day's colour does not snap on. It
*arrives* — a `--dur-arrival` ease from empty to its level. That is the entire acknowledgement.
No checkmark, no toast, no praise. The app quietly takes on the colour of what it was told.

**Stagger, sparingly.** On first paint of a month, cells fade in with ~8ms-per-cell stagger, capped
at ~250ms total. On subsequent navigation, no stagger — a repeated gesture becomes a delay.

**Explicitly vetoed.** A pulsing "today" ring (on an unlogged day it is a nag wearing a pretty
dress). Streaks, completion rings, confetti. These are behaviour decisions, already ruled out by §2.

**Reduced motion.** `prefers-reduced-motion` collapses everything to opacity-only cross-fades at
`--dur-base`. Feedback is never removed, only displacement. The colour still arrives; it does not
travel.

---

## 4. Tokens

Tokens **set shadcn's contract** rather than living beside it: `--background`, `--foreground`,
`--primary`, `--muted`, `--border`, `--ring`, `--radius` are defined in `:root` and `.dark`. Petrol
becomes `--primary`. One thing is added that shadcn has no opinion about: the intensity ramp.

```
--radius: 0.5rem                   /* 8px — shadcn default */
--primary: petrol 600
--intensity-0 … --intensity-5      /* 0 = no entry */
--dur-micro | --dur-base | --dur-spatial | --dur-arrival
--ease-quiet: cubic-bezier(0.32, 0.72, 0, 1)
--ease-exit:  cubic-bezier(0.4, 0, 1, 1)
```

### The ramp is defined twice, not inverted

In light mode intensity climbs by getting **darker**. In dark mode it climbs by getting
**lighter**. A programmatic lightness flip breaks this. The empty cell is near-neutral in both
modes, never pure grey, never the same value as level 1.

**Starting values (light, on `#FBFBFA`):**

| Level | Hex | Foreground | Contrast |
|---|---|---|---|
| 0 (empty) | `#EFF1F1` | `#16201F` @ 30% | decorative |
| 1 | `#E2EEEE` | `#16201F` | pass |
| 2 | `#BEDBDA` | `#16201F` | pass |
| 3 | `#92C0BF` | `#16201F` | 8.29:1 |
| 4 | `#66A19F` | `#16201F` | 5.66:1 |
| 5 | `#3F7D7B` | `#FFFFFF` | 4.79:1 |

**Starting values (dark, on `#131314`):** `#1B1F1F` (empty), `#1E2E2E`, `#2A4646`, `#3A6362`,
`#4E8483`, `#68A7A5`. Level 4 was **retuned in SUI-9** — see below.

### Two known defects — resolved in Ticket B (SUI-31)

1. **The approved mockup put white text on level 4** (`#66A19F`). White on that swatch is **2.93:1
   — fails WCAG AA**. The correct rule is **dark ink through level 4, white only at level 5**.
   The mockup is wrong; this table is right.
2. **The dark ramp has a contrast dead zone at level 4** (`#4E8483`): white gives 4.24:1 and dark
   ink gives 3.91:1 — *both fail AA for small text*. A mid-tone teal cannot carry AA small text
   with either foreground, so no single swatch retune fixes it without either forcing a lightness
   jump or moving text off the swatch.

**Resolution (SUI-31).** Legibility is **decoupled from the swatch**: the day number lives in a
small **neutral, bordered chip** (`--background` fill, `--border` hairline, `--foreground` number,
tabular figures), so contrast is guaranteed at every intensity — the same treatment on every cell,
no threshold switch. This lets **both ramps keep their smooth, even topography** (§2's priority):

- **Light ramp — values unchanged.** The "dark ink through level 4, white at level 5" rule holds
  on the swatch itself (verified: L4 ink `#16201F` = 5.68:1, L5 white = 4.74:1), and the chip gives
  the number ≥16:1 regardless.
- **Dark ramp — values unchanged** (`#1B1F1F #1E2E2E #2A4646 #3A6362 #4E8483 #68A7A5`). The dead
  zone at L4 no longer carries text; the chip does, at ≥15:1.

Implemented as `resources/js/components/suivre/day-cell.tsx`; ramp tokens live in
`resources/css/app.css` as `--intensity-0…5`, defined twice (light/dark), never inverted.

### Final ramp values — closed in SUI-9

SUI-31's chip decoupled legibility on the **calendar cell**, but the `IntensityPicker` puts the
rating digit **on the swatch itself**, so the dead zone had to be closed rather than routed around.
D20's carry-forward is resolved here, measured rather than eyeballed:

- **Dark level 4: `#4E8483` → `#558C8A`.** The old value was a genuine dead zone (white 4.24:1,
  dark ink 3.91:1 — both fail AA). Lifting its relative luminance to 0.225 puts dark ink at
  **4.69:1** and moves the dark scheme's ink threshold to step 4.
- **Light ramp: unchanged.** The published values were already correct; only the mockup was wrong.
  The rule stands — dark ink `#16201F` through level 4 (L4 = 5.68:1), white at level 5 (4.74:1).

**The ink rule is a property of the step, not of the hue.** Every ramp in the app is built to one
relative-luminance profile per step, so one rule serves petrol and all seven condition hues. It
lives in PHP as `RampStep::ink(ColorScheme)` and in CSS as `--ramp-ink-0…5`:

| Scheme | Steps 0–3 | Step 4 | Step 5 |
|---|---|---|---|
| Light | `#16201F` | `#16201F` | `#FFFFFF` |
| Dark | `#EDEEEE` | `#101917` | `#101917` |

`tests/Feature/Enums/ConditionHueTest.php` computes WCAG contrast for every step of every ramp in
both schemes and fails below AA (4.5:1), and separately asserts that `app.css` declares exactly the
values the PHP enums do — so the two homes of a hex cannot drift.

### Type

Inter, served via Bunny (already the project's font provider). `font-variant-numeric: tabular-nums`
globally on data surfaces — day numbers, intensities, sample sizes, lift percentages — so figures
align in columns and do not shimmer when they change.

### Condition hues are chosen, not picked

SUI-8 gives each `Condition` a user-set `color`. A free colour picker kills the system: a user
picks neon magenta, the ramp construction breaks, dark-mode contrast fails. Users choose from a
**curated set of six to eight hues**, each shipping a pre-built five-step ramp built exactly as
petrol's is. Petrol is reserved for the application itself and is not offered as a condition hue.

**Shipped in SUI-9: seven hues** — `App\Enums\ConditionHue`, mirrored client-side as a literal
union in `resources/js/types/conditions.ts` and as CSS `[data-hue='…']` blocks in `app.css`.

Each ramp is **generated, not picked**: for every step, the hue's OKLCH angle is held fixed at
petrol's chroma for that step and its lightness solved so the swatch lands on **petrol's relative
luminance**. Contrast is a pure function of luminance, so every hue inherits petrol's contrast
behaviour exactly and the one ink rule above covers all of them. Worst measured contrast across the
whole set: **4.70:1 light, 4.65:1 dark** (AA is 4.5:1).

| Hue | OKLCH angle | Light 0–5 | Dark 0–5 |
|---|---|---|---|
| Clay | 62° | `#F2F0EF #F3EAE4 #E5D2C2 #D0B29A #B49071 #906C4C` | `#201D1B #342921 #4F3E2F #705842 #9D7C60 #BB9474` |
| Ochre | 95° | `#F1F0EF #EEEBE2 #DBD6BF #C1B795 #A2966B #7E7346` | `#1F1E1B #2E2B1F #47412D #645C3E #8D825B #A89C6E` |
| Moss | 140° | `#F0F1EF #E7EDE5 #CADAC6 #A6BEA1 #819E7B #5D7A57` | `#1C1E1C #252E23 #374534 #4D6249 #6F8969 #85A47E` |
| Marine | 230° | `#EFF1F1 #E4EDF3 #C1D9E5 #97BDD0 #6E9EB5 #487A91` | `#1B1E20 #202D34 #2E4550 #406171 #5D899E #71A3BC` |
| Indigo | 268° | `#F0F1F2 #E8EBF5 #CDD5EB #AAB7D8 #8796BE #63739A` | `#1D1F21 #272B37 #3A4154 #515C77 #7483A7 #8B9BC6` |
| Violet | 305° | `#F1F1F2 #EDEAF3 #DBD1E7 #C0B1D2 #A18FB8 #7E6B94` | `#1F1E20 #2E2A35 #463E51 #635773 #8C7BA0 #A894BF` |
| Plum | 340° | `#F2F1F1 #F3E9EF #E6CFDD #CFAEC3 #B48AA5 #906781` | `#211E1F #33282F #4F3C48 #705366 #9D7890 #BB8FAC` |

Three constraints the test enforces, not the eye: **no hue in the red band** (12°–50°, D20's "no
red, ever"), **every hue ≥30° from petrol** (~193°) so a condition never reads as the app itself,
and **each ramp monotonic** in its scheme's direction. Step 0 is deliberately near-neutral in every
hue — an unrated condition reads as quiet, not as a faint version of itself.

---

## 5. Component inventory

**From shadcn**, in `resources/js/components/ui/`, unmodified where possible: button, input, label,
form, select, checkbox, radio-group, switch, textarea, dialog, drawer, dropdown-menu, popover,
tooltip, badge, card, separator, skeleton, sonner, tabs, slider, avatar, alert, sidebar, combobox.

**Ours**, in `resources/js/components/suivre/`: `TabBar`, `MonthGrid`, `DayCell`,
`IntensityLegend`, `ScalePicker` (3-point mood/sleep/stress), `IntensityPicker` (0–10),
`InsightCard`, `LagHeatmap`, `EmptyState`.

These nine are the product. They would have been ours in any stack.

### Amendment to D8

D8 requires one charting library across Filament widgets and the user app. That is no longer
possible: Recharts (which shadcn's chart wraps) is React-only; Filament's widgets are ApexCharts
and are configured, not authored.

**Amended:** Recharts in the user app, governed by the `dataviz` skill for palette and
accessibility. Filament's stock charts in the backstage, ungoverned. D8's rationale — cohesion for
a solo maintainer — survives, because charts are *authored* in exactly one place. Two libraries,
one authored.

---

## 6. Shell architecture

### Routing

| Route | Name | Notes |
|---|---|---|
| `/` | `home` | Redirect only — guest to `login`, member to `calendar`, admin to the panel. No public marketing (D6). |
| `/calendar/{month?}` | `calendar` | Landing route. `month` is `YYYY-MM`, validated. Ticket B ships shell + placeholder body; SUI-6 fills it. |
| `/day/{date}` | `day` | Defined now so SUI-7 does not move URLs. Placeholder in Ticket B. |
| `/insights` | `insights` | Placeholder. The tab ships now; nav shape must not change when E4 lands. |
| `/settings/*` | — | Unchanged, on the React kit's pages. |

`config/fortify.php` `home` → `/calendar`. The `dashboard` route is retired. `/admin` untouched.

Path segments rather than query params: deep-linkable, survive the PWA `start_url`, and the back
button does the right thing for month navigation.

### Layout

`AppLayout` is an **Inertia persistent layout**. If the layout remounts on navigation the tab bar
re-animates on every tab change, and §3's "motion explains" premise collapses. Persistent layout
keeps the chrome in place; only content cross-fades.

- **Mobile:** `<TabBar>` fixed to the bottom, with `padding-bottom: env(safe-area-inset-bottom)`.
  Without that inset the iPhone home indicator overlaps the tabs — and this is an installable PWA.
- **Desktop:** shadcn `sidebar` in icon-rail mode.
- One `<nav>` definition, two presentations. Three destinations: Calendar, Insights, Settings.

### "Today" is server-derived

`new Date()` in the browser uses the **device** timezone. SUI-1 established that a Suivre day is
midnight in the user's **configured** timezone, and the two diverge the moment the user travels.

The shell receives `today` as a shared Inertia prop, computed server-side via the existing
`ResolveUserDay` action. **The client never derives a date.**

---

## 7. Sequencing — two sub-issues under SUI-5

### Ticket A — Migrate the user-facing app to Inertia + React + shadcn

Install Inertia and TypeScript. Adopt the React starter kit's `resources/js/{pages,layouts,
components}` for auth, 2FA, passkeys and settings. Point Fortify's view responses at Inertia.
Delete `livewire/flux`, `resources/views/pages/**`, the Flux layouts, components and published
stubs. Keep `livewire/livewire` and `livewire/blaze` — Filament needs them. Rewrite the UI section
of `.ai/guidelines/architecture.blade.php`; retire the `livewire-development` and
`fluxui-development` skills.

Port `tests/Feature/Auth/*` and `tests/Feature/Settings/*` from `livewire()` component calls to
HTTP + `assertInertia`.

**Exit criteria.** Login, registration, email verification, password reset, password confirmation,
2FA and passkeys all work exactly as they do today. All three settings pages work. `/admin` is
unaffected. `herd composer check` is green. **No product surface changes** — if a screenshot of
`/settings/security` looks different, the ticket overreached.

**Blocks Ticket B.**

### Ticket B — Design tokens and the app shell

Tokens into `resources/css/app.css`: petrol ramp for both modes (resolving the two contrast defects
in §4), radius, motion tokens, tabular figures. Build `TabBar`, the desktop icon rail, and
`AppLayout` as a persistent layout. Wire the routes in §6 with placeholder pages for calendar, day
and insights. Repoint Fortify `home`. Retire `dashboard`.

**Exit criteria.** SUI-5's stated criteria — guest redirected to login, authenticated user lands on
the shell at `/calendar`, `/admin` unaffected — plus: the tab bar honours safe-area insets;
`prefers-reduced-motion` is respected; every interactive element clears 44×44px; both ramp defects
from §4 are fixed and the resolved values are documented back into this spec.

---

## 8. Testing

`tests/Feature/DashboardTest.php` already encodes SUI-5's acceptance criteria. It becomes
`ShellTest` rather than being deleted.

**Server contract — Pest, and the bulk of the coverage:**

```php
$this->actingAs($user)->get('/calendar')
    ->assertInertia(fn (Assert $page) => $page
        ->component('calendar')
        ->where('today', '2026-07-09')
    );
```

Cases: guest → login redirect; authed `/` → `/calendar`; `/admin` still 200s for an authed user;
`/calendar/2026-13` is rejected; **`today` respects a user whose timezone differs from the
server's** — write that one first, it is the trap from §6.

**Component behaviour — Vitest + React Testing Library**, small and targeted: `TabBar` marks the
correct tab active per route; `DayCell` renders the correct ramp step per level and clears 44px.
Not a broad JS suite — the two components with logic.

**Gate.** `herd composer check` remains the single command. `npm run check` (tsc + vitest) is
folded into it, so one invocation gates Pint, PHPStan level 9, Pest, types and JS tests. Two
commands means one of them stops being run.

**Not now.** Pest 4 browser tests belong to SUI-7's two-tap flow, where there is an interaction
worth driving. Playwright to assert a tab bar exists is theatre.

---

## 9. Non-goals

- PWA manifest and service worker (E6 / SUI-27, SUI-28).
- The calendar month grid itself (SUI-6) and the day view (SUI-7). This spec ships placeholders and
  fixes their URLs.
- Offline capture. Explicitly out of scope for MVP; the Inertia + React stack does not block it
  later, which is a dividend of the switch rather than its motivation.
- Filament backstage changes of any kind.
- The condition hue palette's final six-to-eight values (needed by SUI-8, not by SUI-5).

## 10. Open questions

- ~~Exact dark-ramp values at levels 4–5, pending contrast resolution (Ticket B).~~ **Resolved
  (SUI-31):** dark-ramp values kept as §4; legibility moved into a neutral bordered chip.
- ~~Whether `DayCell` shows the day number inside the filled cell at high intensity, or moves it
  out.~~ **Resolved (SUI-31):** the number sits in a neutral bordered chip on *every* cell — neither
  bare-on-swatch nor moved outside — so the treatment is uniform across intensities.
- Motion library: Motion (`motion.dev`) versus the View Transitions API for the day-cell shared
  element. **Still open** — SUI-31's shell motion is CSS motion tokens only (tab active state,
  content cross-fade); the shared-element choice defers to SUI-6/SUI-7, where the interaction
  worth driving exists. Both satisfy §3.
