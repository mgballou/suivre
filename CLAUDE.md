<laravel-boost-guidelines>
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

- **`architectural-sensibility.md`** (repo root) — the authoritative spec for how this codebase is built: invokable **Actions** carry all business logic; **enums** are domain primitives with predicate + set helpers; **domain events** decouple side-effects; **policies** return `Response`; strict Eloquent; PHPStan **level 9**. Where it is more specific than the Boost guidelines, it wins.
- **`docs/decisions/decision-log.md`** (D1–D13) — every major product/architecture decision and its reasoning. Consult before revisiting a settled choice.
- **`docs/superpowers/specs/2026-07-06-suivre-mvp-design.md`** — the MVP design spec.

## Toolchain — always use Herd

- Run **all** PHP / Composer / Artisan commands through Herd: `herd php artisan …`, `herd composer …`. **Never** bare `php` / `composer`. This overrides every Boost guideline that shows bare `php artisan` or `vendor/bin/…`.
- Local services, including **Postgres**, are managed by Herd (`herd start`, `herd db`).

## Architecture

- **Filament 5 is the operator backstage only** — data oversight, taxonomy/catalog curation, the classification review queue, runtime settings, internal dashboards. It is **not** the end-user UI.
- The **user-facing app is bespoke Livewire 4 (native single-file components) + Flux + Tailwind**, delivered as an installable **PWA** (online-first for MVP).
- Both share the domain layer defined in `architectural-sensibility.md`.

## Quality gates

- `herd composer check` runs **Pint + PHPStan (level 9) + tests**. Keep all three green. There is **no PHPStan baseline** — fix causes, never suppress.
- Pest 4, tests mirror source paths. The suite runs on **sqlite `:memory:`**; Postgres-only features (e.g. `pg_trgm` used by the food classifier) need a Postgres-backed test or an abstraction.

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
- The authoritative expansion of these rules is `architectural-sensibility.md` at the repo root.

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
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

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

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

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

</laravel-boost-guidelines>
