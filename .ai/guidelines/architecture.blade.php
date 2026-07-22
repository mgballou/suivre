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

@verbatim
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
@endverbatim

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
- There is one **role** distinction, via **spatie/laravel-permission** (D23): an `admin` reaches the
  Filament backstage (`User::canAccessPanel()` gates on it) and reads every user's records; a base
  user has no backstage access. `view` policies allow an admin any record, owners only their own;
  admins never mutate user-generated data. Keep policies at this level — the wildcard/scoped
  **per-record** machinery (`posts.*.update`, `canOrElse`, `SyncScopedPermissions...`) is still
  **deferred**; don't build it now, but always return `Response` so the seam exists.

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
