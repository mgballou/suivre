# Suivre — MVP Design Spec

- **Date:** 2026-07-06
- **Status:** Approved (design); scaffolding in progress
- **Decision log:** see `docs/decisions/decision-log.md` (D1–D12) for the reasoning behind each choice

---

## 1. Overview

Suivre is a **personal food-and-symptom journal** focused on correlating dietary and lifestyle inputs with **flare-ups of inflammatory conditions and mood** — explicitly *not* calorie counting or fitness tracking. It is a reboot of an earlier, mostly-stubbed React/Vite + Hono/Drizzle monorepo, rebuilt on Laravel using the team's `architectural-sensibility.md` conventions.

The product's defining value is **insight**: surfacing which foods/triggers tend to precede a user's flare-ups, accounting for time lag. Logging exists to feed that insight, so it must stay low-friction while producing clean, correlatable signal.

## 2. Goals & Non-Goals

### MVP Goals
- A **day-centric journal** with a calendar as the primary UI (period/fertility-tracker paradigm).
- **Two-tap daily check-in:** sleep, mood, stress.
- **User-defined conditions** with daily 0–10 intensity ratings, plus **acute flare events**.
- **Food logging** that resolves entries to a curated **category/trigger taxonomy** via a deterministic classifier.
- **Correlation insights:** lag-lift ranking (trigger tag × condition) with sample sizes, plus a timeline/heatmap.
- **Operator backstage** (Filament) for taxonomy/catalog curation, a classification review queue, oversight, settings, and internal dashboards.
- Installable **PWA**, usable on iPhones without native development.

### Non-Goals (explicitly OUT of MVP)
AI/LLM logging or tagging; offline capture; photo logging; meds/supplements tracking; significance testing / regression confounder-control; recursive ingredient graphs; data sharing between users; public onboarding/marketing. Each is deferred, not designed out — the data/architecture leaves room.

## 3. Principles (North Star)

1. **Insights-first, logging in service of signal** (D2). Every logging decision is judged by whether it yields clean, correlatable data without adding friction.
2. **Time-lag correlation** (D3). Triggers surface hours-to-days later; never correlate on same-timestamp only.
3. **Controlled vocabularies** (D9). Trigger tags are a curated, research-based taxonomy; conditions are user-defined. Stable tags = honest correlation.
4. **Team domain sensibility retained** (D1). Actions carry all business logic; enums are domain primitives; domain events decouple side-effects; policies return `Response`; strict Eloquent; PHPStan level 9; `declare(strict_types=1)`. Only "Filament is the entire UI" is set aside.
5. **Hypothesis-generating, not diagnostic** (D11). Insights show uncertainty (sample size, baseline) and never claim causation.

## 4. Architecture (D1, D7)

> **Scaffolded stack (2026-07-06):** Laravel **13.18**, Livewire **4.3** (single-file components native — the "Volt" style is built in; no separate package), **Flux 2** UI kit (ships with the Livewire starter kit), Filament **5.6**, Fortify auth, Pest 4 + Larastan, Pint. Newer than originally assumed; see decision log **D13**.

- **One Laravel 13 application.**
- **User-facing app:** bespoke **Livewire 4 (native single-file components) + Flux + Tailwind + Alpine**, installable **PWA**, **online-first** (no offline in MVP).
- **Operator backstage:** **Filament 5** panel — taxonomy & catalog curation, classification review queue, user/data oversight, runtime settings (Spatie Settings), internal chart/stat dashboards.
- **Shared domain layer** underneath both UIs, per `architectural-sensibility.md`: `app/Services/{Domain}/Actions`, `Enums/`, `Events/{Domain}/`, `Observers/`, `Listeners/{Domain}/`, `Jobs/{Domain}/`, `Policies/`, `Models/` (+ `Concerns/`, `Scopes/`, `Collections/`), `Settings/`. Directory skeleton scaffolded; `ModelServiceProvider` (strict mode) and `RelationServiceProvider` (morph map) in place.
- **Database:** **PostgreSQL** (D-default) — chosen for `pg_trgm` trigram fuzzy matching in the classifier and stronger analytical/array/jsonb support. **Note:** the Pest suite runs on sqlite `:memory:`; Postgres-specific features (e.g. `pg_trgm`) will need a Postgres test DB or an abstraction when the classifier is built.
- **Auth:** Laravel's official **Livewire starter kit** (Fortify under the hood).

## 5. Data Model (MVP)

All user data is **scoped by `user_id`** from day one (D6). Enums are backed domain primitives with Filament metadata + predicate/set helpers.

| Entity | Purpose | Key fields |
|---|---|---|
| `User` | Auth + owner of all journal data | timezone, settings |
| `DailyCheckin` | The two-tap daily ritual, one per (user, date) | `date`, `sleep` (enum scale), `mood` (3-point enum), `stress` (enum scale), `note?` |
| `Condition` | User-defined outcome to track | `name`, `color`, `icon`, `is_active` |
| `ConditionLog` | Daily intensity rating per condition | (user, condition, `date`), `intensity` 0–10, `value?`, `note?` |
| `FlareEvent` | Acute flare occurrence (hybrid half of D5) | condition, `occurred_at`, `intensity`, `duration?`, `note?` |
| `Meal` | A logged eating occasion | `eaten_at`, `meal_type` (enum) |
| `FoodEntry` | Line item within a meal | → `FoodItem?` (or free text pending classification), `quantity?`, `note?` |
| `FoodItem` | **Global** food catalog (bootstrapped from Open Food Facts) | `name`, `aliases[]`/synonyms, ↔ `Category` (many-to-many) |
| `Category` | **Global** curated trigger taxonomy | `name`, `group`, `description`, `research_source` |
| `ReviewItem` | Classifier miss queued for operator curation | source text, candidate matches, `status` (enum) |

- **Day boundary** = user-local midnight; timezone stored per user; configurable later (D-default).
- Correlation is **computed on-demand** — no stored correlation tables in MVP (D11).
- **Enums (initial):** `MealType`, `MoodLevel` (3-point), `SleepQuality`, `StressLevel`, `CategoryGroup`, `ReviewStatus`.

## 6. Food Classification Pipeline (D9, D10)

1. **Input:** free-text food entry (or picked catalog item).
2. **Normalize:** lowercase, strip, stem/singularize.
3. **Resolve:** synonym/alias lookup → exact catalog match → `pg_trgm` fuzzy match above a similarity threshold.
4. **Tag:** matched `FoodItem` contributes its `Category` tags to the entry.
5. **Miss / low-confidence:** entry goes to the **`ReviewItem`** queue; operator classifies it in Filament, growing the catalog.

- **Catalog seeding:** import **Open Food Facts** (structured allergen/ingredient/additive data) as the matching/autocomplete backbone. **Auto-derive** the "easy" tags on import (allergens → dairy/gluten/nuts/soy/egg; ingredient/additive scans → caffeine, added sugar, alcohol). Curated taxonomy stays authoritative; research-based categories the dataset can't provide (histamine, nightshade, FODMAP) are curated + filled via the review queue.
- **Caveats (carry to implementation):** validate OFF licensing/attribution; name coverage ≠ tag coverage; import needs a dedup + normalization pass; large catalog storage is accepted.
- **No AI in MVP.** The classifier is deterministic and inspectable. An LLM fallback is left as a *gated, far-future* option for genuine catalog gaps only.

## 7. Correlation Engine (D3, D11)

- For each `trigger tag × condition`: compare condition intensity on days **0–N after** the tag was consumed vs. the user's **baseline** (tag-free days). **Rank tags by the lift**, always displaying **sample size (n)**.
- **Lag window** is an operator setting (Spatie Settings; default e.g. 0–2 days).
- Paired with a **timeline/heatmap** visualization (tags vs. condition intensity over time).
- **Presentation:** hypothesis-generating, uncertainty-forward ("suggestive, not proof", small-n flagged) — never causal/diagnostic.
- **Compute:** on-demand for MVP; can move to a scheduled per-user precompute job later.
- **Deferred:** significance testing / regression confounder-control (sleep, stress).

## 8. UI

### 8.1 Bespoke user app (Livewire/Volt PWA)
- **Calendar** (month view, per-day markers for check-in/meals/flares).
- **Day view** — check-in (sleep/mood/stress), the day's meals, condition ratings, flare events; add flows.
- **Meal entry** — free-text → classifier → confirm/edit tags.
- **Insights** — lag-lift ranking + timeline heatmap.
- **Onboarding** — define your conditions.

### 8.2 Filament backstage (operator)
- `Category` taxonomy management; `FoodItem` catalog + `ReviewItem` queue.
- User/data oversight resources.
- Runtime **Settings** (lag window, feature flags).
- Internal chart/stat **dashboards**.

### 8.3 Visualization (D8)
- One charting standard across Filament widgets + the bespoke app (**ApexCharts** or **Chart.js**, final pick TBD), plus a dedicated **calendar-heatmap** component.
- Governed by the **`dataviz`** skill (one accessible, light/dark system); bespoke UI polish via **`frontend-design`**.

## 9. Cross-Cutting

- **Multitenancy:** user-scoped everything; real auth from day one; no public signup/onboarding yet (D6).
- **Timezone / day boundary:** per-user timezone; day = local midnight.
- **PWA:** web manifest + service worker (app-shell caching only in MVP; no offline data capture).
- **Type safety / quality:** `Model::shouldBeStrict()`, PHPStan level 9 (Larastan), Pint (laravel preset + overrides), Pest 4 tests mirroring source paths.

## 10. Skills & Tooling

- **Design/build:** `frontend-design`, `dataviz`, `laravel:laravel-simplifier`.
- **QA:** `branch-qa-filament`, `click-test`.
- **Process:** superpowers set — `writing-plans`, `executing-plans`, `test-driven-development`, `systematic-debugging`, `verification-before-completion`, `requesting-code-review` / `receiving-code-review`.
- **Team workflow skills** the user will pull in (starting work, worktrees, screenshots, PRs) integrate on top of the scaffolding.
- **Proposal:** use `skill-creator` to turn `architectural-sensibility.md` into an **always-on project skill** so the conventions are enforced automatically.

## 11. Repo & Tracking (D12)

- **Code:** private **GitHub** repo.
- **Tickets/board:** **Linear** (free tier), with **Linear↔GitHub sync** so branches/PRs link to issues.
- **Hosting:** **deferred** until deployable (candidates: Laravel Cloud / Forge+VPS / Fly.io); requirement: HTTPS + domain for PWA install.

## 12. Local Environment Notes

- Present: PHP 8.4, Composer 2.9, Laravel Installer 5.28, Node 24, git.
- **Missing:** local Postgres (`psql`) — provision via Herd/Sail/Postgres.app before first migration; `gh` CLI — optional, install if we want GitHub automation.

## 13. Open Questions / To Confirm During Build

- Final charting library (ApexCharts vs Chart.js) — decide when building the first insight view.
- Open Food Facts import scope + licensing/attribution specifics.
- Local Postgres provisioning method (Herd vs Sail vs Postgres.app).
- Exact sleep/stress scale granularity (binary vs 3-point) — settle in the check-in build.
