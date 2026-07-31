# Suivre — MVP Roadmap (Epics)

Six epics decomposed from the MVP design spec (`docs/superpowers/specs/2026-07-06-suivre-mvp-design.md`). Build order is top-to-bottom; each epic delivers a working, demoable slice. Detailed, ticket-sized implementation plans are written **per epic** as we reach it (`docs/superpowers/plans/`), not all up front.

**Linear mapping:** Project **"Suivre MVP"** (team `SUI`) with one **Milestone per epic** (E1–E6). Tickets are issues in the project, assigned to their epic's milestone.

---

## E1 — Day-centric foundation

The smallest real slice: open the PWA and log a two-tap daily check-in on a calendar.

- `User` timezone; day = user-local midnight.
- Enums: `MoodLevel` (3-point), `SleepQuality`, `StressLevel`.
- `DailyCheckin` (one per user/date): sleep, mood, stress, optional note.
- **Calendar UI** (Inertia + React 19 + shadcn/ui, period-tracker style) + day view + check-in flow.

**Depends on:** scaffold (done).

## E2 — Conditions & flares

- User-defined `Condition` (name, colour from the curated `ConditionHue` set, active).
- `ConditionLog` — daily 0–10 intensity per condition.
- `FlareEvent` — acute flare (condition, occurred_at, intensity, note).
- UI to define conditions (onboarding) and rate/log them per day.

**Depends on:** E1.

## E3 — Food & taxonomy

- Enum: `MealType` (breakfast/lunch/dinner/snack).
- `Category` curated trigger taxonomy (Filament backstage).
- `FoodItem` global catalog, bootstrapped from **Open Food Facts** (auto-derive allergen/ingredient tags).
- Deterministic classifier (normalize → synonym → `pg_trgm` fuzzy match) + `ReviewItem` queue.
- `Meal` / `FoodEntry` logging UI with confirm/edit tags.

**Depends on:** E1. (Independent of E2.)

## E4 — Correlation & insights

- Lag-lift ranking Action (`trigger tag × condition`, baseline vs days 0–N after, sample sizes). Default window **0–3** and the whole lag profile out to a week, not a single `N`; below ~**90 days** of logging it returns an explicit insufficient-data state rather than a ranking (SUI-36).
- Insights UI: ranked "suspects" + timeline/heatmap. Hypothesis-generating framing. Co-occurring tags fall back to **coarse pattern-level** attribution, never a single accused tag (D24); user-driven disambiguation is post-MVP.

**Depends on:** E2 + E3 (needs logged data to correlate).

## E5 — Backstage & settings

- Filament oversight resources (users, check-ins, meals, conditions).
- Runtime Settings (Spatie): correlation lag window, feature flags.
- Internal chart/stat dashboards.

**Depends on:** E2–E4.

## E6 — PWA

- Web manifest + service worker (app-shell caching; online-first, no offline data capture in MVP).
- Installability polish (icons, iOS add-to-home-screen behavior).

**Depends on:** E1.

---

## Sequencing

E1 first (smallest real thing on the phone). E2 and E3 are independent and both feed **E4** (the payoff). E5 and E6 are polish layered on top. Each epic is its own spec→plan→tickets cycle; we refine details per epic rather than up front.
