# Suivre

Suivre is a **personal food-and-symptom journal**. It correlates diet and lifestyle inputs
(food, sleep, stress) with **inflammatory flare-ups and mood** through lag-aware,
**hypothesis-generating** insights — a trigger on one day may surface as a flare hours to days
later. It is explicitly **not** calorie counting or fitness tracking.

Insights are the north star: logging is designed to produce clean, correlatable signal, and
results are always framed as suggestive, never diagnostic.

## Architecture

Two UIs sit on **one shared domain layer**:

- **Operator backstage** — **Filament 5** on `/admin` (stays on Livewire). Data oversight,
  taxonomy/catalog curation, the classification review queue, runtime settings, internal
  dashboards. Not the end-user UI.
- **User-facing app** — a bespoke **Inertia + React 19 + TypeScript + shadcn/ui + Tailwind**
  application, delivered as an installable **PWA** (online-first for MVP).
- **Domain layer** underneath both — invokable single-purpose **Actions** carry all business
  logic; **enums** are domain primitives (predicate + set helpers); **domain events** decouple
  cross-domain side-effects; **policies return `Response`**; **strict Eloquent**; **PHPStan
  level 9** with no baseline.

See `CLAUDE.md` (the woven `.ai/*` guidelines) for the authoritative build spec.

## Stack

- **PHP 8.4**, **Laravel 13**
- **Inertia 3** + **React 19** + **TypeScript**, **Tailwind v4**, built with **Vite** and typed
  via **Laravel Wayfinder**
- **Filament 5** (operator backstage; keeps Livewire 4 for itself)
- **Laravel Fortify** for authentication
- **PostgreSQL** (chosen for `pg_trgm` fuzzy matching in the food classifier)
- **Pest 4** for tests; **Larastan/PHPStan level 9**, **Pint** for style

## Local setup

Everything runs through **Laravel Herd** — always `herd php` / `herd composer`, never bare
`php` / `composer`. Postgres is managed as a Herd service.

```bash
herd composer install
cp .env.example .env      # if .env does not exist
herd php artisan key:generate
npm install
npm run build
herd php artisan migrate
```

The full walkthrough — Postgres service creation, the `suivre_test` database, git hooks, MCP
servers, and worktrees — is in **`docs/local-setup.md`**.

The app is served by Herd at `https://suivre.test`. When you see a Vite manifest error, run
`npm run build` (or `npm run dev`).

## Quality gate

```bash
herd composer check
```

Runs **Pint + PHPStan (level 9, no baseline) + `wayfinder:generate --with-form` + `tsc --noEmit`
+ Pest**. Keep them all green; fix causes, never suppress. Tests run on a dedicated **PostgreSQL**
database (`suivre_test`), not sqlite — Postgres-only features like `pg_trgm` work in both local
(Herd) and CI (`postgres:18`).

`main` is protected by a local pre-push hook — branch off, push the branch, and open a PR
(`gh pr create`); CI runs on every PR.

## Where to look next

- **`docs/decisions/decision-log.md`** — every major product/architecture decision and its reasoning.
- **`docs/roadmap.md`** — the MVP epics (E1–E6) and build order.
- **`CLAUDE.md`** — the authoritative day-to-day architecture spec and conventions.
