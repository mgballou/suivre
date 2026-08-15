# Suivre

A personal food-and-symptom journal. It records what you eat, how you slept and how stressed
you were, alongside daily condition ratings and mood, and looks for **lag-aware** correlations
between them — a trigger on one day may surface as a flare hours or days later. Results are
framed as suggestive, never diagnostic. It is not calorie counting and not fitness tracking.

The repository is a working Laravel 13 application, not a demo. It is public as a sample of
how I build.

## Start with the decision log

**[`docs/decisions/decision-log.md`](docs/decisions/decision-log.md)** is 50 KB and 27 numbered
entries recording every significant product and architecture decision on this project. Each
entry states the decision, the reasoning behind it, and — the part that does the work — what it
**rules out**. It is append-only and newest-last, so a later entry supersedes an earlier one
rather than editing it. Reversals stay visible: D7 chose Livewire for the user-facing app and
D19 replaced it with Inertia + React, and both are still there with the argument that moved
between them.

It is the most unusual thing in the repository, and the fastest way to judge the codebase. If
you only read one file, read that one.

Two conventions follow from it. `CLAUDE.md` is generated from the guideline sources in `.ai/`
and is the day-to-day build spec — the rules the code is actually held to. When a decision
invalidates a line in there, correcting it is part of the same change, because the guidelines
are what gets read and acted on while the decision log only explains why.

## Architecture

Two front ends sit on one shared domain layer.

- **Operator backstage** — Filament 5 at `/admin`, on Livewire. Data oversight, taxonomy and
  food-catalog curation, the classification review queue, internal dashboards. Deliberately not
  the end-user interface.
- **User-facing app** — bespoke Inertia 3 + React 19 + TypeScript + shadcn/ui + Tailwind v4,
  delivered as an installable PWA.
- **Domain layer** — shared by both, and where the business logic lives.

The two roles reach opposite halves of the application and both directions are enforced: a
member cannot open `/admin`, and an administrator is redirected out of the journal and out of
`/settings`, so it manages its own credentials on Filament's own profile page. There is no
public registration; an account begins in the backstage.

### The domain layer

Business logic lives in single-purpose invokable **Actions** under
`app/Services/{Domain}/Actions/` — `LogMeal`, `ClassifyFoodEntry`, `ComputeCorrelations`.
One public `__invoke`, named arguments at the call site, resolved through the container at the
point of use rather than constructor-injected, so it stays obvious that control is handed to a
separate class. Everything else is a shell that composes them: Filament actions, jobs,
listeners, policies and controllers hold no logic of their own.

Around that:

- **Enums are domain primitives**, not string constants. Each carries its predicates and the
  matching set helper — `isActive()` alongside `getActiveStatuses()` — so a call site reads the
  filter off the enum instead of reproducing it. They implement Filament's `HasLabel`,
  `HasColor` and `HasIcon`, so badges and selects render from the same source.
- **Domain events decouple side effects.** An observer turns a raw model lifecycle hook into a
  meaningful event; queued listeners react. Events fired inside a transaction implement
  `ShouldDispatchAfterCommit`.
- **Policies return `Response`, never `bool`**, so the reason for a denial reaches the UI, and
  the policy stays the single place a precondition is expressed.
- **Strict Eloquent** is on globally — no lazy loading, no missing attributes, no silent
  mass assignment. It throws locally and reports in production.
- **PHPStan level 9 with no baseline.** Nothing is suppressed.

The full set of rules, including the anti-patterns each of these exists to prevent, is in
`.ai/guidelines/architecture.blade.php`.

### Typed routes via Wayfinder

The React side never hard-codes a URL. **Laravel Wayfinder** reads the route and controller
definitions and generates TypeScript functions into `resources/js/routes` and
`resources/js/actions`, which the app imports as `@/routes/*` and `@/actions/*`. Renaming a
route or changing a parameter surfaces as a type error at `tsc` rather than a 404 in the
browser.

The generated output is gitignored. It is produced by the Vite plugin during a build and by
`herd php artisan wayfinder:generate --with-form`, which the quality gate runs before `tsc` —
otherwise there would be nothing to typecheck against.

### PWA delivery

The user app is installable and online-first. `resources/views/app.blade.php` links a
`/manifest.webmanifest`; `public/sw.js` and `resources/js/lib/service-worker.ts` handle
registration and update. iOS ignores the manifest for add-to-home-screen, so the Apple-specific
meta tags are set explicitly alongside it. Offline support is out of scope for now.

Dates are a deliberate constraint: the client never derives one. `new Date()` reads the device
timezone rather than the account's configured one, so every date, month and label arrives from
the server already formatted, and the journal stays keyed on the account holder's local day.

## Stack

- PHP 8.4, Laravel 13, Octane (FrankenPHP)
- PostgreSQL — chosen for `pg_trgm`, which backs fuzzy matching in the food classifier
- Inertia 3, React 19, TypeScript, Tailwind v4, Vite, Wayfinder
- Filament 5 for the backstage; Livewire 4 stays installed for it
- Laravel Fortify for authentication; spatie/laravel-permission for roles
- Pest 4, Larastan/PHPStan level 9, Pint

## Quality gates

```bash
herd composer check
```

Runs Pint, PHPStan level 9, `wayfinder:generate --with-form`, `tsc --noEmit` plus vitest, and
Pest, in that order. Tests run against a dedicated PostgreSQL database (`suivre_test`) rather
than sqlite, so Postgres-only behavior is exercised by the suite instead of abstracted around.

CI mirrors that gate across parallel jobs — `static-analysis` (PHPStan), `frontend` (tsc +
vitest) and `test` (Pest on `postgres:18`) in `tests.yml`, plus `quality` (Pint `--test`) in
`lint.yml`. A green CI run means the
same thing as a green local gate. The pre-push hook can be bypassed with `--no-verify`; CI
cannot, so it is the real gate. Deploys run from GitHub Actions and only after the test
workflow has succeeded on `main`, so a red `main` never ships.

## Local setup

Everything runs through Laravel Herd — `herd php` and `herd composer`, not bare `php` or
`composer`. Postgres is a Herd service.

```bash
herd composer install
cp .env.example .env          # if .env does not exist
herd php artisan key:generate
npm install && npm run build
herd php artisan migrate
```

Served at `https://suivre.test`. A Vite manifest error means the front end has not been built —
run `npm run build` or `npm run dev`.

The full walkthrough — Postgres setup, the `suivre_test` database, git hooks, worktrees — is in
[`docs/local-setup.md`](docs/local-setup.md).

## Repository map

| Path | What is in it |
|---|---|
| `docs/decisions/decision-log.md` | The 27 recorded decisions. Start here. |
| `CLAUDE.md` · `.ai/guidelines/` | The build spec the code is held to; `CLAUDE.md` is generated from `.ai/`. |
| `app/Services/{Domain}/Actions/` | Business logic, one Action per file. |
| `app/Filament/` | Operator backstage. Schemas extracted per resource. |
| `resources/js/pages/` · `components/suivre/` | Inertia pages and product components. |
| `docs/roadmap.md` | MVP epics and build order. |
| `docs/superpowers/specs/`, `plans/` | Dated, point-in-time design artifacts. Each carries a status banner saying whether it still applies. |
| `tests/` | Pest, mirroring the source paths. |

## License

MIT — see [`LICENSE`](LICENSE).
