<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\FoodItem;
use Illuminate\Support\Facades\DB;

/**
 * The trigram substrate SUI-16's deterministic classifier is built on (D9).
 * These assert the index exists and that similarity ranks the way the
 * classifier will depend on — not that a particular plan is chosen, which
 * Postgres is free to change on a small table.
 */
function indexDefinition(string $table, string $index): ?string
{
    /** @var object{indexdef: string}|null $row */
    $row = DB::selectOne(
        'SELECT indexdef FROM pg_indexes WHERE tablename = ? AND indexname = ?',
        [$table, $index]
    );

    return $row?->indexdef;
}

it('has a GIN trigram index on the catalog normalized name', function (): void {
    $definition = indexDefinition('food_items', 'food_items_normalized_name_trigram_index');

    expect($definition)->toContain('USING gin');
    expect($definition)->toContain('gin_trgm_ops');
    expect($definition)->toContain('normalized_name');
});

it('has a GIN trigram index on the synonym normalized alias', function (): void {
    $definition = indexDefinition('food_item_aliases', 'food_item_aliases_normalized_alias_trigram_index');

    expect($definition)->toContain('USING gin');
    expect($definition)->toContain('gin_trgm_ops');
    expect($definition)->toContain('normalized_alias');
});

it('ranks catalog names by trigram similarity to a query string', function (): void {
    $exact = FoodItem::factory()->named('Dark chocolate')->createQuietly();
    $near = FoodItem::factory()->named('Dark chocolate bar')->createQuietly();
    $distant = FoodItem::factory()->named('Cheddar cheese')->createQuietly();

    $ranked = FoodItem::query()
        ->whereIn('id', [$exact->id, $near->id, $distant->id])
        ->orderByRaw('similarity(normalized_name, ?) DESC, id ASC', ['dark chocolate'])
        ->pluck('id')
        ->all();

    expect($ranked)->toBe([$exact->id, $near->id, $distant->id]);
});

it('scores a misspelling above an unrelated food', function (): void {
    $target = FoodItem::factory()->named('Spaghetti bolognese')->createQuietly();
    $unrelated = FoodItem::factory()->named('Greek yoghurt')->createQuietly();

    $scores = FoodItem::query()
        ->whereIn('id', [$target->id, $unrelated->id])
        ->selectRaw('id, similarity(normalized_name, ?) AS score', ['spagetti bolognaise'])
        ->pluck('score', 'id');

    expect((float) $scores[$target->id])->toBeGreaterThan(0.4);
    expect((float) $scores[$unrelated->id])->toBeLessThan(0.1);
});

it('matches a synonym through the same normalized trigram query', function (): void {
    $foodItem = FoodItem::factory()->named('Eggplant')->withAliases(['Aubergine'])->createQuietly();

    $matched = DB::table('food_item_aliases')
        ->where('food_item_id', $foodItem->id)
        ->whereRaw('similarity(normalized_alias, ?) > 0.4', ['aubergene'])
        ->exists();

    expect($matched)->toBeTrue();
});
