# Suivre — Project Rules

These project rules take precedence over the generic Laravel Boost guidelines wherever they conflict.

## Product

Suivre is a **personal food-and-symptom journal** that correlates diet/lifestyle inputs with **inflammatory flare-ups and mood** through lag-aware, **hypothesis-generating** insights. It is explicitly **not** calorie counting or fitness tracking.

## Authoritative references (read before non-trivial work)

- **The `.ai/architecture` rules** (woven into this file) — the authoritative spec for how this codebase is built: invokable **Actions** carry all business logic; **enums** are domain primitives with predicate + set helpers; **domain events** decouple side-effects; **policies** return `Response`; strict Eloquent; PHPStan **level 9**. Where it is more specific than the generic Boost guidelines, it wins.
- **`docs/decisions/decision-log.md`** — every major product/architecture decision and its reasoning. Consult before revisiting a settled choice.
- **`docs/superpowers/specs/2026-07-06-suivre-mvp-design.md`** — the MVP design spec.

## Toolchain — always use Herd

- Run **all** PHP / Composer / Artisan commands through Herd: `herd php artisan …`, `herd composer …`. **Never** bare `php` / `composer`. This overrides every Boost guideline that shows bare `php artisan` or `vendor/bin/…`.
- Local services, including **Postgres**, are managed by Herd (`herd start`, `herd db`).

## Architecture

- **Filament 5 is the operator backstage only** — data oversight, taxonomy/catalog curation, the classification review queue, runtime settings, internal dashboards. It is **not** the end-user UI.
- The **user-facing app is bespoke Inertia + React 19 + TypeScript + shadcn/ui + Tailwind**, delivered as an installable **PWA** (online-first for MVP). Filament stays on Livewire, on `/admin`.
- Both share the domain layer defined in the `.ai/architecture` rules.

## Quality gates

- `herd composer check` runs **Pint + PHPStan (level 9) + `wayfinder:generate` + `tsc --noEmit` + tests**. Keep them all green. There is **no PHPStan baseline** — fix causes, never suppress. Wayfinder's output is gitignored, so it must be generated before `tsc` can typecheck.
- Pest 4, tests mirror source paths. The suite runs on a dedicated **Postgres** database (`suivre_test`); Postgres-only features like `pg_trgm` (used by the food classifier) work in both local (Herd) and CI (`postgres:18`) and need only `CREATE EXTENSION IF NOT EXISTS pg_trgm` in a migration — not an abstraction.
- CI mirrors `herd composer check` across parallel jobs (`static-analysis` = PHPStan L9, `frontend` = `tsc --noEmit` + vitest, `test` = Pest on `postgres:18`, `quality` = Pint `--test`). A green CI run means the same thing as a green local gate; the pre-push hook is bypassable with `--no-verify`, so CI is the non-bypassable gate.

## Pull requests

- **One canonical shape.** Every PR body fills `.github/PULL_REQUEST_TEMPLATE.md` — keep all sections (Ticket · Technical description · Types of changes · Screenshots · Deployment steps). A `pull_request` **pr-lint** check fails any PR whose body lacks a Linear ticket link, a checked "Types of changes" box, or a Deployment decision — so `gh pr create --body …` from an agent must conform, not just the web compose box.
- **Technical description** uses the global `CLAUDE.md` bullet style (`Verb X preposition Y … clause Z`), 5–8 bullets, focused on the branch-vs-`main` diff — not a file inventory. Invoke the **create-pr** skill to build a compliant PR; it resolves the `SUI-<id>` from the branch name and opens a draft.
- For a UI change, offer **capture-screenshots** to generate and embed shots in the Screenshots section.
