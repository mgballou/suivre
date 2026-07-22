<laravel-boost-guidelines>
=== .ai/architecture rules ===

# Architecture

How Suivre is built. This is the authoritative day-to-day spec; where it is more specific
than the generic Boost guidelines, it wins. Code that follows it should feel written by one
hand. Rationale lives in `docs/decisions/decision-log.md`, not here.

## Layering

Two UIs sit on one shared domain layer:

- **Filament 5 = operator backstage only** — data oversight, taxonomy/catalog curation, the
  classification review queue, runtime settings, internal dashboards. **Not** the end-user UI.
- **User-facing app = bespoke Inertia + React 19 + TypeScript + shadcn/ui + Tailwind**,
  shipped as an installable **PWA** (online-first for MVP).
- **Domain layer** underneath both: invokable Actions, enums, events, observers, listeners,
  jobs, policies, models. Business logic lives here — never in a Filament Action, Livewire
  component, job, or listener; and in a controller only below the extraction criteria in the
  user-facing layer section.

Information flows down (UI → Action → Model); side-effects flow back up through domain events.
The Action is the only place that orchestrates more than one thing at a time.

## Actions — the spine

**All business logic lives in single-purpose, invokable Actions** under
`app/Services/{Domain}/Actions/`. Everything else (Filament Actions, Livewire components, Jobs,
Listeners, Commands, Policies) is a thin shell that composes them.

<code-snippet name="Action shape" lang="php">
namespace App\Services\Meals\Actions;

class LogMeal
{
    public function __invoke(User $user, MealData $data): Meal
    {
        $meal = $user->meals()->create([/* ... */]);

        dispatch(new ClassifyMealJob(meal: $meal));

        return $meal;
    }
}
</code-snippet>

- **One public method, `__invoke`.** Helpers are private.
- **Imperative `{Verb}{Noun}` names**: `LogMeal`, `ClassifyFoodEntry`, `CheckUserHasActiveFlare`.
  If you can't name it `{Verb}{Noun}` without contortion, it isn't an Action.
- **Named parameters at every non-trivial call site.**
- **Resolve via the container, not `new`, and not constructor injection** — call
  `app(CheckSomething::class)($x)` at the point of use; type-hint on `handle()` in Jobs/Listeners;
  type-hint as a callback param in Filament. **In controllers, resolve inline with
  `app(SomeAction::class)(...)` rather than injecting the Action into `__construct`.** A
  constructor-injected `($this->someAction)(...)` reads like a method on `$this` but is not one —
  keeping the `app(...)` call at the call site makes it unmistakable that control is handed to a
  separate Action, and keeps where logic lives visible from where it is invoked.
- **Return `void`, a model, a primitive, or a `readonly` DTO — never an array.**
- **Throw typed domain exceptions** via `throw_if(condition: ..., exception: SomeException::make($x))`.
- **Not** an Action: a trivial query (belongs on the model), a pure transformation (a Factory
  or accessor), a getter-style read (a Repository).

## Models

Models describe state and provide read-side derivations. They never orchestrate multi-step flows.

- **Strict mode is global** (`Model::shouldBeStrict()` in `ModelServiceProvider`) — no lazy
  loading, no missing attributes, no silent mass-assignment. Violations throw in
  `local`/`testing`, report in `production`.
- **Observers via the `#[ObservedBy(...)]` attribute**, not the service provider.
- **Generic-typed relationships** — `/** @return BelongsTo<User, $this> */` on every relation.
- **Cast to enums and value casts everywhere** — no raw strings/ints for status, no floats for
  currency. Explicitly cast integer columns (`'intensity' => 'integer'`) — uncast attributes are
  `mixed` and fail PHPStan L9.
- **Accessors are one-liners** via `Attribute::get(...)`; compose complex strings/values in a
  Factory or Action. When an accessor touches a relationship, guard with
  `$this->relationLoaded('rel')` and backfill with `setRelation()` at every render/iterate site
  (strict mode throws in prod on a missed site).
- **Scopes are invokable classes** in `Models/Scopes/`, applied with `->tap(new ActiveScope())`.
- **`getMorphClass()` is the single source of truth** for polymorphic strings; the morph map
  lives in `RelationServiceProvider::boot()` via `Relation::enforceMorphMap([...])` and matches
  table names. The morph map is mandatory.

## Enums as domain primitives

Enums carry state-machine predicates, display metadata, and set helpers — not just values.

- Implement Filament's `HasLabel`, `HasColor`, `HasIcon` (and `HasDescription` where useful) so
  badges/selects/infolists render automatically. When a `TextColumn`/`TextEntry` is `->badge()`
  on a column cast to the enum, Filament auto-resolves label/color/icon — do not re-specify them.
- **Pair every predicate with a complementary set helper**: `isActive()` ↔ `getActiveStatuses()`.
  Never reproduce that filter at a call site — read it from the enum.
- Predicates are spelled `isThing()` / `hasThing()` / `canThing()`.
- Compare through predicates, not `=== Status::Foo`, except the most trivial single-state check.
- Type array shapes in PHPDoc: `@return array<int, self>`.

## Domain events

Events decouple cross-domain reactions to a state change.

- **Observer** translates raw model lifecycle into a *meaningful* event (stamps derived fields in
  `updating()`, then dispatches `SomethingChanged`). It does not call mailers/jobs directly.
- **Event**: one file per event in `Events/{Domain}/`, `readonly`-style public props (model +
  before/after values), `use Dispatchable, SerializesModels`, and **always**
  `implements ShouldDispatchAfterCommit` (so events fired inside a transaction run only on commit).
- **Listener**: `implements ShouldQueue`, one concern each, a single `handle()` that delegates to
  an Action or Mailable. No logic beyond delegation.
- **Job**: thin `ShouldQueue` wrapper that resolves an Action in `handle()` and invokes it — queue
  semantics only (tries/timeouts/batching), never business logic.

## Authorization

- **Policies return `Response`, never `bool`** — the deny message reaches the UI. Domain
  preconditions (e.g. state checks) belong in the policy, the single source of truth; the UI never
  re-implements them.
- MVP is **single-user**: keep policies simple. The team's wildcard/scoped per-record permission
  machinery (`posts.*.update`, `canOrElse`, `SyncScopedPermissions...`) is **deferred until Suivre
  goes multi-user** — don't build it now, but still return `Response` so the seam exists.

## User-facing layer (Inertia + React + shadcn/ui + PWA)

- **Inertia 3 + React 19 + TypeScript.** Pages live in `resources/js/pages/`, resolved by component
  name (`settings/profile` → `resources/js/pages/settings/profile.tsx`). Keep state server-side;
  validate and authorize inside Actions and Form Requests exactly as an HTTP request would.
- **shadcn/ui** (new-york, neutral, `--radius: 0.5rem`) in `resources/js/components/ui/`, unmodified
  where possible. Product components live in `resources/js/components/suivre/`.
- **Wayfinder** generates typed route helpers into `resources/js/{routes,actions}` — gitignored, and
  regenerated by `herd php artisan wayfinder:generate --with-form` and by the Vite plugin. Import
  routes from `@/routes/*`; never hard-code URL strings.
- **Tailwind v4** for layout. Match sibling components before writing custom styles.
- Controllers stay thin. Extract an Action when the logic is (1) complex enough to warrant its own
  class, (2) needed by both the Filament backstage and the user app, or (3) reused across call sites
  within one of them. Do not extract on principle alone.
- Adding a page? Run `npm run build` before Pest — `app.blade.php` code-splits per page, so a page
  missing from the Vite manifest makes its route 500.
- Delivered as an installable **PWA**, online-first for MVP.
- Insights/visualisations are a first-class product surface — keep them easy and central.
- **Filament 5 remains on Livewire**, on `/admin`, and is untouched by this. `livewire/livewire` and
  `livewire/blaze` stay installed for it.

## Providers

| Provider | Responsibility |
|---|---|
| `ModelServiceProvider` | `Model::shouldBeStrict()` + violation handlers |
| `RelationServiceProvider` | `Relation::enforceMorphMap([...])` — mandatory, matches table names |
| `AppServiceProvider` | Scoped singletons (repositories), top-level boot wiring |

## Naming cheat sheet

| Concept | Pattern | Example |
|---|---|---|
| Service Action | `{Verb}{Noun}` | `LogMeal` |
| Action (predicate) | `Check{Subject}{Condition}` | `CheckUserHasActiveFlare` |
| Domain Exception | `{Reason}Exception` + `::make(...)` | `MealNotEditableException` |
| Domain Event | `{Subject}{Verbed}` | `MealClassified` |
| Listener | `{Verb}{Subject}...Listener` | `RecomputeCorrelationsOnMealListener` |
| Job | `{Action}Job` | `ClassifyMealJob` |
| Observer / Policy | `{Model}Observer` / `{Model}Policy` | `MealObserver` / `MealPolicy` |
| Filament Resource | `{Model}Resource` | `MealResource` |
| Schema classes | `{Model}Form` · `{Plural}Table` · `{Model}Infolist` | in `Schemas/` |
| Filament Action | `{Verb}{Noun}Action` | `ClassifyMealAction` |
| DTO / Cast / Enum | `{Subject}Data` · `{Type}Cast` · domain noun | `MealData` · `MoneyCast` · `FlareIntensity` |

## Anti-patterns

- Business logic in a Filament component, Job, or Listener — or in a controller once it meets the
  extraction criteria in the user-facing layer section above.
- Multi-step orchestration in a model method or accessor.
- `bool` returns from policies (always `Response`).
- Hand-rolled morph strings instead of `getMorphClass()` + the enforced morph map.
- Inline FQCN; mocking Models; `->and()` in Pest; `Carbon` instead of `CarbonImmutable`.
- Comparing enum states with `=== Status::Foo` when an `isFoo()` predicate exists.
- Filament form/table/infolist defined inline on the Resource.
- Throwing bare `Exception` with a hard-coded string instead of a typed `::make()` exception.
- Forgetting `ShouldDispatchAfterCommit` on an event fired inside a transaction.
- Logic in a Job's `handle()` beyond resolving and invoking an Action.

## Related guidelines (do not duplicate here)

- **Code style, providers, type-safety, morph map** — see the `.ai/suivre` rules.
- **Testing** — see the `.ai/testing-conventions` and `.ai/filament-testing` rules.
- **DTOs (Spatie Data)** — see the `.ai/spatie-data` rules.
- **Quality gate**: `herd composer check` = Pint + PHPStan (level 9, no baseline) + `wayfinder:generate`
  + `tsc --noEmit` + Pest. Keep them all green; fix causes, never suppress. Tests run on a dedicated
  **Postgres** database (`suivre_test`) — Postgres-only features like `pg_trgm` work in both local
  (Herd) and CI (`postgres:18`) and need only `CREATE EXTENSION IF NOT EXISTS pg_trgm`, not an abstraction.

=== .ai/code-review-graph rules ===

# MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

> Local dependency: the `code-review-graph` CLI must be installed and the graph
> built for these tools to be available (see `docs/local-setup.md`). This
> guideline is the source of truth for the instructions — Laravel Boost weaves it
> into `CLAUDE.md`, so do not re-add it to that file by hand.

## When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

## Key Tools

| Tool | Use when |
|------|----------|
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

## Workflow

1. The graph auto-updates on file changes (via `.claude/settings.json` hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.

=== .ai/documentation rules ===

# Documentation & decision hygiene

Suivre's recurring failure mode is **stale instructions**. A decision lands in the decision-log, but
the docs agents actually load each session — the woven `.ai/guidelines/*` → `CLAUDE.md` — keep
saying the old thing. Under an agent-driven workflow that stale line is then acted on confidently at
scale (e.g. building the `pg_trgm` abstraction D18 forbids, because a note still said tests run on
sqlite). Keeping the docs in sync is part of every change's **definition of done**, not a later cleanup.

Durable, always-loaded instructions to agents live in **`.ai/guidelines/*`** (woven into
`CLAUDE.md` / `AGENTS.md` by Boost) — never in `.claude/`, which holds machine-local settings and
Boost-generated symlinks, not authored guidance.

## Source-of-truth hierarchy

| Layer | Role | Drift rule |
|---|---|---|
| `.ai/guidelines/*` → woven `CLAUDE.md`/`AGENTS.md` | **The live authority.** Loaded every session; agents act on it directly. | Must contain **zero** statements a later decision contradicts. This is the layer that must never drift. |
| `docs/decisions/decision-log.md` | The **reasoning record** — each decision, why, and what it rules out. Newest appended at the bottom. | Append-only; never rewritten. Records what was true when written; newer entries win. |
| `docs/roadmap.md` | Current epic / build plan — what we build next. | Kept current: a stale stack, scope, or dependency line here misleads the next ticket. |
| `docs/superpowers/specs/*`, `docs/superpowers/plans/*` | **Dated, point-in-time artifacts.** | Not edited to stay current. Each carries a **Status banner** (below); when superseded, the banner points forward and the body is left as the historical record. |
| Linear label / project / ticket descriptions | Cross-references agents read when picking up work. | Swept alongside the docs when a decision invalidates them. |

The **real files in the repo** are the source of truth for configuration (`pint.json`, `phpstan.neon`,
`composer.json`, CI workflows). Never restate their contents in prose — a copy only drifts (D16).

## Authoring guidelines & skills — edit the source in `.ai/`, never the generated `.claude/` copy

Both the woven guidelines and the skills have a **source** in `.ai/` and a Boost-generated presence
under `.claude/`. Always author in `.ai/`; the `.claude/` side is output.

- **Guidelines** — author in `.ai/guidelines/*.blade.php`. Guideline files are **Blade** templates,
  even when the body is plain Markdown (the `.blade.php` extension is the convention — do not add
  `.md` guidelines). The `<laravel-boost-guidelines>` block in `CLAUDE.md` / `AGENTS.md` is
  **generated**; never hand-edit it — Boost's `GuidelineWriter` rewrites the block and drops stray
  edits (D14). After editing a blade, run `herd php artisan boost:update` and commit the regenerated
  `CLAUDE.md`.
- **Skills** — the source of truth is **`.ai/skills/<name>/`**; `.claude/skills/<name>` is a
  committed **symlink** into it. When you add a skill — or want to keep or modify one, **including a
  stock framework skill `boost:update` just pulled in** (those land as a real dir under
  `.claude/skills/`) — put its source in `.ai/skills/<name>/` and make `.claude/skills/<name>` a
  symlink: `ln -s ../../.ai/skills/<name> .claude/skills/<name>`. `boost:update` preserves the
  symlink. **Never commit an authored skill as a real directory under `.claude/skills/`** — that copy
  is not the source and won't survive a rebuild.

Rule of thumb: if a change must persist across `boost:update`, it belongs in `.ai/`. `.claude/` holds
generated output, symlinks, and machine-local settings — nothing authored.

## When a decision lands — the sweep

A decision is not done when it is written in the decision-log. In the **same PR** that enacts it,
sweep everything it invalidates. The decision-log entry's **"Rules out"** line is the checklist:

1. **`.ai/guidelines/*`** — correct every statement the decision changes, then run
   `herd php artisan boost:update` so the committed `CLAUDE.md` matches the blades. Hand-editing
   `CLAUDE.md` instead drifts it from its source and is overwritten on the next regenerate.
2. **`docs/roadmap.md`** — fix any epic whose stack, scope, or dependency changed.
3. **Spec / plan Status banners** — mark any spec or plan the decision supersedes (banner, not rewrite).
4. **Linear** — update the label / project / ticket descriptions the decision now makes wrong.

## Spec & plan Status banner

Every file under `docs/superpowers/specs/` and `docs/superpowers/plans/` opens with a status line so a
reader orients in one glance:

- `- **Status:** Active` — current, not yet superseded.
- `- **Status:** Superseded on <dimension> by <D-number / newer doc>` — e.g.
  `Superseded on stack (D19) & test DB (D18); see docs/…-design-system….md`. The body below is the
  historical record; do not act on the superseded dimensions.

A spec cited under **Authoritative references** in the `.ai/suivre` rules must either be **Active** or
carry a banner scoping exactly which parts still apply.

## Drift smell test — run before opening any PR

- Does any woven guideline sentence contradict the newest decisions? Fix the guideline.
- Did this change settle something a spec / plan / roadmap / Linear label still states the old way?
  Banner or fix it.
- Did you touch `.ai/guidelines/*` without re-running `boost:update`? The committed `CLAUDE.md` is stale.

=== .ai/filament-testing rules ===

# Testing Filament Actions

## Overview

For standard Filament action testing documentation (table actions, bulk actions, schema actions, modals, validation, halted actions, etc.), use `search-docs` with queries like `['testing actions', 'TestAction', 'callAction']`.

This guideline covers project-specific conventions only.

## Prefer Action Classes Over Strings

When a custom action class exists, always use the class in `TestAction::make()` instead of a string name. This improves IDE support and refactoring safety.

<code-snippet name="Prefer action class over string" lang="php">
// ✅ Preferred - use action class
TestAction::make(SendInvoiceAction::class)->table($invoice)

// ❌ Avoid - string names when class exists
TestAction::make('send')->table($invoice)
</code-snippet>

## Variable Assignment for Repeated Actions

**IMPORTANT**: When asserting the same action multiple times in a single test, assign the `TestAction` to a variable to avoid recreation:

<code-snippet name="Single action variable assignment" lang="php">
use App\Filament\Resources\Invoices\Actions\SendInvoiceAction;
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

$invoice = Invoice::factory()->createQuietly();

$action = TestAction::make(SendInvoiceAction::class)->table($invoice);

livewire(EditInvoice::class)
    ->assertActionExists($action)
    ->assertActionVisible($action);
</code-snippet>

## Multiple Actions Naming Convention

When multiple actions exist in a single test, name variables descriptively—but only if used more than once:

<code-snippet name="Multiple action variables" lang="php">
use App\Filament\Resources\Invoices\Actions\EditInvoiceAction;
use App\Filament\Resources\Invoices\Actions\DeleteInvoiceAction;
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

$invoice = Invoice::factory()->createQuietly();

$editAction = TestAction::make(EditInvoiceAction::class)->table($invoice);

livewire(ListInvoices::class)
    // Used once - inline is fine
    ->assertActionExists(TestAction::make(DeleteInvoiceAction::class)->table($invoice))
    // Used multiple times - use variable
    ->assertActionExists($editAction)
    ->assertActionVisible($editAction);
</code-snippet>

## Making Custom Actions Testable by Class Name

For custom action classes with a static `make()` method, add the `#[ActionName]` attribute or a `getDefaultName()` method so Filament can discover the action name without instantiation:

<code-snippet name="ActionName attribute for static make() pattern" lang="php">
use Filament\Actions\Action;
use Filament\Actions\ActionName;

#[ActionName('send')]
class SendInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('send')
            ->requiresConfirmation()
            ->action(fn () => /* ... */);
    }
}
</code-snippet>

<code-snippet name="getDefaultName for classes extending Action" lang="php">
use Filament\Actions\Action;

class SendInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation()
            ->action(fn () => /* ... */);
    }
}
</code-snippet>

## Schema Extraction

- Always extract Filament `form()`, `table()`, and `infolist()` definitions into separate classes, regardless of size.
- Use a consistent naming scheme:
    - `{Model}Form` for form schemas
    - `{ModelPlural}Table` for tables
    - `{Model}Infolist` for infolists
- Place these classes in a resource-specific folder structure: `App/Filament/Resources/{Resource}/Schemas/`.
- Each class must expose a static `configure()` method.
- Call the `configure()` method from the resource to apply the schema or table.
- Do not use a required base class or interface, so `configure()` can accept custom parameters for flexible reuse.

## Filament Enum Badges

- When an enum implements `HasLabel`, `HasColor`, and `HasIcon`, use `->badge()` only on `TextColumn`/`TextEntry`. Filament auto-resolves label, color, and icon from the enum contracts when the model column is cast to the enum. Do not add manual `formatStateUsing()`, `color()`, or `icon()` closures — they are redundant and will drift from the enum's source of truth.

=== .ai/front-end-conventions rules ===

# Front-end Conventions

How React + TypeScript under `resources/js` is written: commenting density, typing, and where a
doc block belongs. Stack, layering and page conventions live in the `.ai/architecture` rules; for
Inertia client patterns activate the `inertia-react-development` skill. This file covers only what
review has actually had to correct.

The failure mode here is **over-commenting**. Prop-by-prop JSDoc that restates the prop name, prose
describing a string format a type could enforce, and doc blocks attached to the wrong symbol — all
appeared in the calendar work and all had to be removed. Default to fewer comments and stronger
types.

## Prefer a type to a comment

If a comment describes the **shape** of a value, encode the shape instead. Template literal types
for formatted strings, literal unions for closed sets.

<code-snippet name="Encode the format, don't describe it" lang="ts">
// ✅ The format is enforced, and a month cannot be passed as a date
export type IsoDate = `${number}-${number}-${number}`;
export type IsoMonth = `${number}-${number}`;

type MonthGridProps = {
    month: IsoMonth;
    previousMonth: IsoMonth;
};

// ❌ Prose the compiler cannot check
type MonthGridProps = {
    /** `YYYY-MM`. */
    month: string;
    previousMonth: string;
};
</code-snippet>

The payoff is not tidiness. `date` and `month` were both `string`, so the compiler would happily
accept one where the other belonged; typing them distinctly made them mutually unassignable.

Be honest about the limit: a template literal constrains **shape, not validity** — `IsoDate` will
accept `2026-13-99`. It stops a category error, not bad data. Validity is the server's job.

## No prop-by-prop JSDoc

Do not interleave comments through a prop list. If a prop needs explaining, the name or the type is
wrong — fix that first. Context that genuinely survives goes on **the type, the component, or the
constant it describes**, never mid-list.

<code-snippet name="Context on the type, not the prop" lang="ts">
// ✅ The level's meaning belongs to the level type
/** Step 0 is an unlogged day; 1–5 climb the ramp. */
export type IntensityLevel = 0 | 1 | 2 | 3 | 4 | 5;

type DayCellProps = {
    date: IsoDate;
    level: IntensityLevel;
    isToday?: boolean;
    hasCheckin?: boolean;
};

// ❌ Restating the name and the type, one prop at a time
type DayCellProps = {
    /** ISO date, `YYYY-MM-DD`. */
    date: string;
    /** 0 = no entry; 1–5 climb the ramp. */
    level: IntensityLevel;
    /** A `DailyCheckin` exists for this date. */
    hasCheckin?: boolean;
};
</code-snippet>

## Comment *why*, not *what*

A comment earns its place only when it records something the reader cannot recover from the code:
a constraint from outside the file, a cross-boundary contract, or a non-obvious reason for a
choice. Real examples from this codebase:

- Ramp classes are listed literally rather than interpolated, **so Tailwind's JIT emits them**.
- Weekdays are Monday-first, and **the server computes `leadingBlanks` from the same convention**,
  so the two must change together.
- Pan direction is read from the previous render, **because Inertia re-renders the component in
  place rather than remounting it**.

Delete anything that narrates the next line (`// Set up listener`, `// Components`).

## Attach the doc to the symbol it describes

A JSDoc block sits immediately above the thing it documents. A component doc attached to the
constant above the component never surfaces on hover — it is worse than no comment, because it
looks maintained. When you move code, move its doc with it and re-check what it is now attached to.

## Types

- Types used by more than one module live in `resources/js/types/` and are re-exported through
  `resources/js/types/index.ts` (`export type * from './dates';`). Import from `@/types`.
- A type owned by one component stays with that component and is exported from it — `IntensityLevel`
  from `day-cell.tsx`, `CalendarDay` from `month-grid.tsx`. Do not promote it until a second module
  needs it.
- Never widen a prop to `string` or `unknown` to silence an error; narrow the source instead.
- A type assertion (`as IsoDate`) is a last resort and is the one place a comment reliably earns its
  keep — say what widened the type and why the assertion is safe.

## Dates come from the server

The client **never derives a date**. `new Date()` reads the device timezone, not the user's
configured one, so every date, month and label arrives as a server prop already formatted. There is
no client-side date maths in the user-facing app; if a view needs a new date, the controller or
Action computes it.

=== .ai/spatie-data rules ===

# Spatie Data Objects

## Overview

This project uses [Spatie Laravel Data](https://spatie.be/docs/laravel-data/v4) for data transfer objects that cross boundaries (gateway returns, computed snapshots, request DTOs). For trivial value objects, plain `readonly` classes are fine.

## Magic Creation Methods (`from*`)

Spatie Data's `from()` method automatically dispatches to magic `from{TypeName}` methods based on the argument type. This means calling `self::from()`, `parent::from()`, or `static::from()` inside a magic creation method with the same type **will cause infinite recursion**.

Always use `new self(...)` with explicit property mapping in magic creation methods.

<code-snippet name="Correct magic creation method" lang="php">
// ✅ Correct — use new self() with explicit mapping
public static function fromMeal(Meal $meal): self
{
    return new self(
        id: $meal->id,
        eatenAt: $meal->eaten_at,
        tags: $meal->categoryTags(),
    );
}
</code-snippet>

<code-snippet name="Incorrect magic creation method" lang="php">
// ❌ Infinite recursion — from() dispatches back to fromMeal()
public static function fromMeal(Meal $meal): self
{
    $data = self::from($meal);
    $data->tags = $meal->categoryTags();

    return $data;
}
</code-snippet>

## Immutability

DTOs are immutable by convention. Mutating helpers belong on Actions or model accessors, not on the DTO.

=== .ai/suivre rules ===

# Suivre — Project Rules

These project rules take precedence over the generic Laravel Boost guidelines wherever they conflict.

## Product

Suivre is a **personal food-and-symptom journal** that correlates diet/lifestyle inputs with **inflammatory flare-ups and mood** through lag-aware, **hypothesis-generating** insights. It is explicitly **not** calorie counting or fitness tracking.

## Authoritative references (read before non-trivial work)

- **The `.ai/architecture` rules** (woven into this file) — the authoritative spec for how this codebase is built: invokable **Actions** carry all business logic; **enums** are domain primitives with predicate + set helpers; **domain events** decouple side-effects; **policies** return `Response`; strict Eloquent; PHPStan **level 9**. Where it is more specific than the generic Boost guidelines, it wins.
- **The `.ai/documentation` rules** — how the docs fit together and the definition-of-done that keeps them from drifting. Read before making, or acting on, a decision that changes a settled fact.
- **`docs/decisions/decision-log.md`** — every major product/architecture decision and its reasoning. Consult before revisiting a settled choice; the newest entries win.
- **`docs/superpowers/specs/2026-07-09-suivre-design-system-and-app-shell-design.md`** — the current design-system & app-shell spec: the *quiet instrument* philosophy and the Inertia + React 19 + shadcn/ui stack.
- **`docs/superpowers/specs/2026-07-06-suivre-mvp-design.md`** — the original MVP product & data-model spec. **Superseded on stack (D19) and test DB (D18)** — the product scope, principles and data model still hold; the Livewire/Flux and sqlite `:memory:` statements do not. Read its Status banner.

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

=== .ai/testing-conventions rules ===

# Testing Conventions

## Overview

For standard Pest and Laravel testing documentation, use `search-docs` with queries like `['pest testing', 'feature tests', 'mocking']`.

This guideline covers project-specific conventions only.

## General Conventions

- Avoid adding comments in tests unless the logic is particularly complex.
- Avoid using `->and()` method in Pest tests. Use separate `expect` assertions for clarity.
- All tests relying on Laravel application boot should be in `tests/Feature`.
- Test file paths should mirror the relative path of the file being tested.
- All test files should have a namespace corresponding to their directory.
- If a test file already exists, run the tests first to see failures before making changes.

## Mocking Conventions

- **Never mock Laravel Models or Model functions.**
- Prefer `->expects('method')` over `->shouldReceive('method')` for readability.
- Use `->expects('method')->never()` to explicitly assert a method should not be called.
- When using `->withArgs` with closures, use assertions directly and `return true`.

<code-snippet name="Mock with withArgs closure" lang="php">
$this->mock(Example::class)
    ->expects('method')
    ->withArgs(function ($arg1, $arg2) {
        expect($arg1)->toBe('expectedValue');
        expect($arg2)->toBeGreaterThan(0);

        return true;
    });
</code-snippet>

## Laravel Fakes Conventions

- Always import full facade namespace: `use Illuminate\Support\Facades\Event;` not `use Event;`
- Place `Exceptions::fake()` in `beforeEach` when asserting exceptions were reported.
- When asserting with closures in Laravel fakes, use assertions directly and `return true`.

<code-snippet name="Laravel fakes with closure assertions" lang="php">
Event::assertDispatched(function ($event) {
    expect($event->property)->toBe('expectedValue');

    return true;
});

Http::assertSent(function (Request $request) {
    expect($request->url())->toBe('expectedValue');

    return true;
});

Queue::assertPushed(function ($job) {
    expect($job->property)->toBe('expectedValue');

    return true;
});

Notification::assertSentTo(function ($notifiable) {
    expect($notifiable->property)->toBe('expectedValue');

    return true;
});
</code-snippet>

## Model Factories Conventions

- Use `createQuietly()` to avoid triggering events during test setup.
- Use `create()` when you need to trigger model events.
- Check for helper methods in factories to set polymorphic relationships. Create the helper if it doesn't exist.

<code-snippet name="Polymorphic relationship factory usage" lang="php">
$meal = Meal::factory()->createQuietly();
$entry = FoodEntry::factory()->for($meal)->createQuietly();
$review = ReviewItem::factory()->reviewable($entry)->createQuietly();
</code-snippet>

<code-snippet name="Factory helper for polymorphic relationships" lang="php">
public function reviewable(Model $model): self
{
    return $this->state([
        'reviewable_type' => $model->getMorphClass(),
        'reviewable_id' => $model->getKey(),
    ]);
}
</code-snippet>

## Architecture

- The `app/Services` directory should contain all business/domain logic where possible, grouped by domain/integration as much as possible. Exceptions:
    - Filament related classes, which can remain in `app/Filament`.
    - Laravel-specific classes like Jobs, Commands, etc.
    - Prefer using action classes to extract logic.
- The authoritative expansion of these rules is the `.ai/architecture` rules.

## Database Constraints

- Snapshot or junction tables with a "one per type per parent" pattern must have a composite unique index on `(parent_id, type)` to enforce uniqueness at the database level — not just in application code.

## Code Style

- Always import fully qualified namespaces (including `Throwable`, `Exception`); import Filament components directly.
- Always import classes at the top with `use` statements and reference them by their unqualified (short) class name.
- Fully qualified class names should not be used inline, except in special cases (like dynamic resolution).
- Use `CarbonImmutable` instead of `Carbon` when working with dates. This prevents accidental mutation. Model date columns are automatically cast to `CarbonImmutable` when retrieved.
- All actions should be invokable classes with a single `__invoke` method containing the action logic.
- Prefer the `config()` / `config()->set(...)` helper over the `Config` facade.
- Prefer the `session()` / `session()->put(...)` / `session()->forget(...)` helpers over the `Session` facade.
- All Model morph types should be defined in `Relation::enforceMorphMap()` in the `RelationServiceProvider`.
    - To retrieve a model's morph type, use `$model->getMorphClass()` rather than hardcoding it.
    - The morph type should generally match the model table name.

## Type Safety & PHPStan Compliance

### Configuration Access

- Use typed config methods for type safety instead of generic `config()`:

<code-snippet name="Typed config access" lang="php">
// ✅ Correct — typed config
$timezone = config()->string('app.timezone');
$debug = config()->boolean('app.debug');

// ❌ Avoid — returns mixed
$timezone = config('app.timezone');
</code-snippet>

### PHPDoc Array Types

- Always specify array value types for better static analysis:

<code-snippet name="PHPDoc array shapes" lang="php">
// ✅ Correct
/**
 * @return array<int, string>
 */
public static function encrypted(): array

// ❌ Incomplete
public static function encrypted(): array
</code-snippet>

### Eloquent Column Casts

- Explicitly cast integer columns in `$casts` (e.g. `'intensity' => 'integer'`). Eloquent returns `mixed` for uncast attributes, which fails PHPStan level 9.

### Model Attribute Accessors & Relationships

- When a computed attribute accessor depends on an optional relationship, guard access with `$this->relationLoaded('relation')`. This prevents lazy-loading violations in strict mode. If the relation is not loaded, return a safe default, and document the contract with a test.
- When iterating a collection where each item accesses a parent relationship in a computed attribute, use `setRelation()` to backfill the parent before the loop to prevent N+1 queries:

<code-snippet name="Backfill parent relation to avoid N+1" lang="php">
// ✅ Correct — set relation once, no N+1
$meal->entries->each(fn ($entry) => $entry->setRelation('meal', $meal));

// ❌ Each iteration lazy-loads the parent
foreach ($meal->entries as $entry) {
    $entry->label; // accesses $this->meal->...
}
</code-snippet>

- **After writing an accessor that accesses a relationship**, grep for every place the model is rendered or iterated (Filament `TextEntry`, `RepeatableEntry`, blade loops, collection maps) and add `setRelation()` backfill at each call site. `Model::shouldBeStrict()` is enabled — missed call sites throw in production, not just log.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/octane (OCTANE) - v2
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
