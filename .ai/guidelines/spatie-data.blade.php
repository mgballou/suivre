# Spatie Data Objects

## Overview

This project uses [Spatie Laravel Data](https://spatie.be/docs/laravel-data/v4) for data transfer objects that cross boundaries (gateway returns, computed snapshots, request DTOs). For trivial value objects, plain `readonly` classes are fine.

## Magic Creation Methods (`from*`)

Spatie Data's `from()` method automatically dispatches to magic `from{TypeName}` methods based on the argument type. This means calling `self::from()`, `parent::from()`, or `static::from()` inside a magic creation method with the same type **will cause infinite recursion**.

Always use `new self(...)` with explicit property mapping in magic creation methods.

@verbatim
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
@endverbatim

@verbatim
<code-snippet name="Incorrect magic creation method" lang="php">
// ❌ Infinite recursion — from() dispatches back to fromMeal()
public static function fromMeal(Meal $meal): self
{
    $data = self::from($meal);
    $data->tags = $meal->categoryTags();

    return $data;
}
</code-snippet>
@endverbatim

## Immutability

DTOs are immutable by convention. Mutating helpers belong on Actions or model accessors, not on the DTO.
