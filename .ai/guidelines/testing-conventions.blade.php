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

@verbatim
<code-snippet name="Mock with withArgs closure" lang="php">
$this->mock(Example::class)
    ->expects('method')
    ->withArgs(function ($arg1, $arg2) {
        expect($arg1)->toBe('expectedValue');
        expect($arg2)->toBeGreaterThan(0);

        return true;
    });
</code-snippet>
@endverbatim

## Laravel Fakes Conventions

- Always import full facade namespace: `use Illuminate\Support\Facades\Event;` not `use Event;`
- Place `Exceptions::fake()` in `beforeEach` when asserting exceptions were reported.
- When asserting with closures in Laravel fakes, use assertions directly and `return true`.

@verbatim
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
@endverbatim

## Model Factories Conventions

- Use `createQuietly()` to avoid triggering events during test setup.
- Use `create()` when you need to trigger model events.
- Check for helper methods in factories to set polymorphic relationships. Create the helper if it doesn't exist.

@verbatim
<code-snippet name="Polymorphic relationship factory usage" lang="php">
$meal = Meal::factory()->createQuietly();
$entry = FoodEntry::factory()->for($meal)->createQuietly();
$review = ReviewItem::factory()->reviewable($entry)->createQuietly();
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Factory helper for polymorphic relationships" lang="php">
public function reviewable(Model $model): self
{
    return $this->state([
        'reviewable_type' => $model->getMorphClass(),
        'reviewable_id' => $model->getKey(),
    ]);
}
</code-snippet>
@endverbatim

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

@verbatim
<code-snippet name="Typed config access" lang="php">
// ✅ Correct — typed config
$timezone = config()->string('app.timezone');
$debug = config()->boolean('app.debug');

// ❌ Avoid — returns mixed
$timezone = config('app.timezone');
</code-snippet>
@endverbatim

### PHPDoc Array Types

- Always specify array value types for better static analysis:

@verbatim
<code-snippet name="PHPDoc array shapes" lang="php">
// ✅ Correct
/**
 * @return array<int, string>
 */
public static function encrypted(): array

// ❌ Incomplete
public static function encrypted(): array
</code-snippet>
@endverbatim

### Eloquent Column Casts

- Explicitly cast integer columns in `$casts` (e.g. `'intensity' => 'integer'`). Eloquent returns `mixed` for uncast attributes, which fails PHPStan level 9.

### Model Attribute Accessors & Relationships

- When a computed attribute accessor depends on an optional relationship, guard access with `$this->relationLoaded('relation')`. This prevents lazy-loading violations in strict mode. If the relation is not loaded, return a safe default, and document the contract with a test.
- When iterating a collection where each item accesses a parent relationship in a computed attribute, use `setRelation()` to backfill the parent before the loop to prevent N+1 queries:

@verbatim
<code-snippet name="Backfill parent relation to avoid N+1" lang="php">
// ✅ Correct — set relation once, no N+1
$meal->entries->each(fn ($entry) => $entry->setRelation('meal', $meal));

// ❌ Each iteration lazy-loads the parent
foreach ($meal->entries as $entry) {
    $entry->label; // accesses $this->meal->...
}
</code-snippet>
@endverbatim

- **After writing an accessor that accesses a relationship**, grep for every place the model is rendered or iterated (Filament `TextEntry`, `RepeatableEntry`, blade loops, collection maps) and add `setRelation()` backfill at each call site. `Model::shouldBeStrict()` is enabled — missed call sites throw in production, not just log.
