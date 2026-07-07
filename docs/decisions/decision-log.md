# Suivre — Decision Log

Running record of major decisions made while brainstorming the rebuild. Newest decisions appended at the bottom. Each entry: the decision, the reasoning, and what it rules out. This log feeds the final design spec.

> Context: reviving a personal food-and-symptom journal, previously a mostly-stubbed React/Vite + Hono/Drizzle monorepo (`../suivre-app`). Rebuilding on Laravel, drawing on the team's `architectural-sensibility.md` and the old app's data-model thinking.

---

## D1 — Reboot on Laravel; keep the domain sensibility, drop "Filament is the entire UI"

- **Decision:** Rebuild as a fresh Laravel app. Retain the *entire* domain layer from `architectural-sensibility.md` (invokable Actions carry all business logic, enums as domain primitives, domain events + observers + queued listeners, policies returning `Response`, strict Eloquent, PHPStan level 9, `declare(strict_types=1)`). Set aside **only** the rule "Filament is the entire authenticated UI."
- **Why:** Those domain patterns are stack-agnostic and are the "same team" test. The Filament-as-UI rule is the one part that doesn't fit a phone-first daily journal.
- **Rules out:** Continuing the old React/Hono monorepo; treating this as a pure Filament admin app.

## D2 — North star: correlation insights; logging serves correlatable signal

- **Decision:** The MVP's defining capability is **correlation insights** (surfacing which foods/triggers precede flare-ups/mood dips). Logging is designed *in service of* producing clean, correlatable signal — low-friction enough that data actually accumulates, structured enough to find patterns.
- **Why:** The whole point of the app is the food↔flare relationship. But insights need data, so logging friction is the enemy and must stay low.
- **Rules out:** Building elaborate logging/data-richness for its own sake before there's an insight to justify it.

## D3 — Time-lag correlation model (not naive same-timestamp)

- **Decision:** The correlation engine must model **time lag** — a trigger on day D may surface as a flare hours-to-days later. The old spec's "meal at time T vs symptom at time T" is explicitly rejected.
- **Why:** Inflammatory flare-ups and mood shifts lag their triggers. Same-timestamp correlation would be mostly noise.
- **Rules out:** Correlating only within a single day/timestamp.

## D4 — Tracked variables

- **Decision:** MVP inputs/triggers: **food & drink, stress, sleep.** Outcomes: **flare-ups (configurable conditions) + mood.** Meds/supplements **deferred** (same "logged event" shape, easy to add later).
- **Why:** Honest correlation needs the major confounders (sleep, stress) visible, not just food. Meds add scope without being day-one essential.
- **Rules out:** Nothing permanently — deferred items reuse the event model.

## D5 — Day-centric journal with a calendar front door

- **Decision:** The **day** is the central object; a **calendar is the primary UI** (period/fertility-tracker paradigm — Clue/Flo style). Any day can carry many tagged events or none.
  - **Baked-in daily check-in (fast, two-tap):** sleep (simple good/bad or 3-point scale) and mood (☹️ / 😐 / 🙂 — 3-point).
  - **Configurable conditions:** user-defined (e.g. eczema, joint pain, gut, brain fog), each logged per day with an intensity (0–10) and possibly other values.
  - **Food & acute flares:** events attached to a day.
- **Why:** Matches how these symptoms are lived and how proven tracker apps work; day-level time-series is ideal for lag correlation.
- **Rules out:** A CRUD-list-first admin paradigm as the primary user experience.

## D6 — Multi-user eventually; user-scoped from day one

- **Decision:** Scope every table by user and use real auth from the start. MVP is two users (you + partner), each a fully private journal. No public onboarding/marketing yet, but no architecture that blocks it.
- **Why:** Cheap to scope by user now; expensive to retrofit multi-tenancy later.
- **Rules out:** Single-global-user shortcuts; building public signup/onboarding now.

## D7 — Frontend: Livewire/Volt bespoke PWA for users; Filament as operator backstage

- **Decision:** Build the user-facing journal as a **bespoke Livewire/Volt + Tailwind + Alpine PWA** (home-screen installable, online-first for MVP). Keep **Filament as an internal operator backstage**: data oversight, reference-data management, runtime settings (incl. correlation parameters via Spatie Settings), and near-free internal dashboards. Two audiences, two UIs, one shared domain layer.
- **Why:** One language/mental model, same engine as Filament (cohesion), least tooling for essentially one maintainer, matches the team's server-driven style. React/Inertia's main edge (rich offline) isn't needed now.
- **Offline:** Explicitly out of scope for MVP (online-first). Can be added later if missed.
- **Rules out:** Inertia+React, Inertia+Vue (Vue eliminated by preference), and native mobile development.

## D8 — Charting standard + visualization approach

- **Decision:** Standardize on **one charting library** used across both Filament widgets and the bespoke app (candidate: **ApexCharts** — Filament's default — or **Chart.js**; final pick TBD). Add a dedicated **calendar-heatmap** component for the period-tracker-style heatmap view. Govern all visuals with the **`dataviz` skill** (one coherent, accessible, light/dark system) and use the **`frontend-design` skill** for the bespoke UI polish. Lean on Filament widgets for internal dashboards.
- **Why:** Insights/visualizations are a priority and the maintainer is "no frontend guru" — vetted, themeable, AI-friendly libraries + a design-system skill keep us out of hand-rolled D3.
- **Rules out:** Per-view ad-hoc charting libraries; bespoke low-level D3 as the default.

## D9 — Curated taxonomy + deterministic classifier; AI is NOT in the MVP

- **Decision:** Trigger tags come from a **curated, research-based taxonomy** (operator-owned reference data, managed in Filament, global/shared, stable) — not user-invented and not AI-generated. Food entries are classified by a **deterministic classifier**: normalize text → resolve synonyms/aliases → fuzzy-match (e.g. Postgres `pg_trgm` trigram similarity) against a curated **food/dish → category knowledge base**, with curated compositions for common composite dishes. Unmatched/low-confidence entries go to a **human-in-the-loop review queue** in the backstage, which grows the KB over time. **No AI/LLM in the MVP loop** — left open only as a gated last-resort fallback for genuine catalog gaps far later, never the backbone.
- **Why:** A controlled, stable tag vocabulary is what makes correlation statistically honest; free-form or AI-sprawled tags fragment the signal. "AI was big when I started this" is the same cargo-cult trap as "the old app used React." A deterministic, inspectable classifier is more trustworthy for data whose whole value is its cleanliness.
- **Separation of vocabularies:** **Conditions** (outcomes) remain user-defined per D5; **trigger tags** (food inputs) are the curated global taxonomy. Different governance.
- **Rules out:** Fully user-defined trigger tags; uncontrolled AI auto-tagging; an AI gateway as core logging infrastructure in MVP.

## D10 — Bootstrap the food catalog from Open Food Facts; auto-derive the "easy" tags; curate the rest

- **Decision:** Seed the food catalog by importing a public dataset with **structured allergen/ingredient data** (primary candidate: **Open Food Facts**; USDA FoodData Central as secondary for ingredient statements). On import, **auto-derive tags** where the source gives real signal — allergen tags → dairy/gluten/nuts/soy/egg; ingredient/additive scans → caffeine, added sugar, alcohol. The **curated taxonomy remains authoritative**; research-based categories the dataset can't provide (histamine, nightshade, FODMAP) are curated + filled via the review queue. Accept a one-time **dedup/noise-pruning pass** and the storage cost of a large catalog.
- **Why:** Broad name coverage makes entry/autocomplete "just work" for any food without waiting for the queue to grow, and a structured source hands us the common allergen categories for free. Chosen over the leaner curated-only approach for coverage; the smart-bootstrap framing avoids the "coverage of names ≠ coverage of tags" trap.
- **Caveats to carry into implementation:** validate dataset licensing/attribution; name coverage ≠ tag coverage (insights still depend on curated + auto-derived tags, not raw catalog size); import needs dedup + normalization.
- **Rules out:** Lean curated-only seeding; importing a nutrition-only dataset with no allergen/ingredient structure.

## D11 — MVP insight = lag-lift ranking + supporting visualization

- **Decision:** For each `trigger tag × condition`, compare condition intensity on days 0–N after the tag was consumed vs. the user's baseline (tag-free days), and **rank tags by the lift**, always displaying **sample size (n)**. Pair with a **timeline/heatmap** (tags vs. condition intensity over time). The **lag window is configurable** (operator setting via Spatie Settings, default e.g. 0–2 days). Present results as **hypothesis-generating and explicitly uncertain** — sample sizes shown, "suggestive, not proof" framing — never as diagnosis.
- **Why:** Personal daily data over months is small and confounded; descriptive lag-lift delivers a real, explainable ranked "suspects" list without the false rigor that p-values invite on tiny n. It's the sweet spot for the insights-first north star (D2) with the lag model (D3).
- **Compute model (implementation note):** on-demand computation when viewing insights for MVP (data is small); can move to a scheduled per-user precompute job later if needed.
- **Deferred:** significance testing / regression confounder-control (sleep, stress) is a post-MVP enhancement once enough data exists.
- **Rules out:** Shipping raw visualizations with no computed ranking; presenting correlations as causal/diagnostic.

## D12 — Hosting deferred; task tracking on GitHub

- **Decision (hosting):** Defer the hosting/deploy choice until there's a deployable app. Candidates to revisit then: Laravel Cloud (lowest ops), Forge + cheap VPS (team standard), Fly.io/Railway. Requirement when we pick: HTTPS + a domain for PWA install.
- **Decision (tracking):** Code in a **private GitHub repo**; tickets + board in **Linear** (free tier), with **Linear↔GitHub sync** so branches/PRs link to issues. Linear provides the cards/columns/priority planning the user wants.
- **Why:** Linear's board/keyboard UX is preferred; free tier is sufficient; GitHub sync keeps code and tickets linked. Hosting has no bearing on the build phase, so choosing a host now would be premature.
- **Rules out (for now):** GitHub Projects/Issues-only as the tracker; committing to a host before there's something to ship.

## D13 — Actual scaffolded stack (2026-07-06): Laravel 13 / Livewire 4 / Flux / Filament 5

- **Decision (reality-check):** The Laravel installer produced a newer stack than assumed. Adopted as-is: **Laravel 13.18**, **Livewire 4.3** (single-file components are native — the "Volt" style is built in; there is no separate `livewire/volt` package), **Flux 2** UI kit (ships with the Livewire starter kit), **Filament 5.6** (verified to resolve cleanly against Livewire 4 — no conflict), **Fortify** auth (no WorkOS), **Pest 4** + Larastan, **Pint**.
- **Scaffolding completed:** app merged into repo root (existing `docs/`, `.claude/`, `architectural-sensibility.md` preserved); naming set to Suivre; domain-layer directory skeleton created per §3; `ModelServiceProvider` (strict mode + lazy-loading violation handler) and `RelationServiceProvider` (empty morph map) added and registered; `pint.json` + `phpstan.neon` aligned to the team reference (**PHPStan level 9 green, zero baseline**; Pint green; **33/33 tests pass**). Laravel **Boost deferred** (`--no-boost`) to the AI-docs phase. Run all PHP/Composer via **Herd** (`herd php`, `herd composer`).
- **Carry-forward note:** Pest runs on sqlite `:memory:`; the food classifier's `pg_trgm` matching will need a Postgres test DB or an abstraction to be testable.
- **Why:** Newer versions are current as of the build date, resolve cleanly together, and give better cohesion (bespoke app + Filament both on Livewire 4) plus Flux's vetted components — a win for a non-frontend-guru maintainer.
- **Rules out:** Downgrading to Laravel 12 / Livewire 3; a separate Volt package.
