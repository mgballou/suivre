<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\CategoryGroup;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('persists a category', function (): void {
    $category = Category::factory()->createQuietly();

    expect($category->exists)->toBeTrue();
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'slug' => $category->slug,
    ]);
});

it('casts group to the CategoryGroup enum', function (): void {
    $category = Category::factory()->group(CategoryGroup::Research)->createQuietly();

    expect($category->fresh()->group)->toBe(CategoryGroup::Research);
});

it('is global — the table carries no owning user', function (): void {
    expect(Schema::hasColumn('categories', 'user_id'))->toBeFalse();
});

it('enforces a unique slug at the database level', function (): void {
    Category::factory()->createQuietly(['slug' => 'histamine']);

    expect(fn () => Category::factory()->createQuietly(['slug' => 'histamine']))
        ->toThrow(QueryException::class);
});

it('allows a null research source', function (): void {
    $category = Category::factory()->createQuietly(['research_source' => null]);

    expect($category->fresh()->research_source)->toBeNull();
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new Category())->getMorphClass())->toBe('categories');
});
