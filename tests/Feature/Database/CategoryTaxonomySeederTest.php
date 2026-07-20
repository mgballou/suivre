<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\CategoryGroup;
use App\Models\Category;
use Database\Seeders\CategoryTaxonomySeeder;

it('seeds the curated baseline taxonomy', function (): void {
    $this->seed(CategoryTaxonomySeeder::class);

    expect(Category::query()->count())->toBe(11);

    foreach (['dairy', 'gluten', 'nuts', 'soy', 'egg'] as $slug) {
        expect(Category::query()->where('slug', $slug)->value('group'))
            ->toBe(CategoryGroup::Allergen);
    }

    foreach (['caffeine', 'added-sugar', 'alcohol'] as $slug) {
        expect(Category::query()->where('slug', $slug)->value('group'))
            ->toBe(CategoryGroup::Ingredient);
    }

    foreach (['histamine', 'nightshade', 'fodmap'] as $slug) {
        expect(Category::query()->where('slug', $slug)->value('group'))
            ->toBe(CategoryGroup::Research);
    }
});

it('cites a research source for every research category', function (): void {
    $this->seed(CategoryTaxonomySeeder::class);

    $uncited = Category::query()
        ->where('group', CategoryGroup::Research->value)
        ->whereNull('research_source')
        ->count();

    expect($uncited)->toBe(0);
});

it('is idempotent', function (): void {
    $this->seed(CategoryTaxonomySeeder::class);
    $this->seed(CategoryTaxonomySeeder::class);

    expect(Category::query()->count())->toBe(11);
});

it('covers every category group', function (): void {
    $this->seed(CategoryTaxonomySeeder::class);

    foreach (CategoryGroup::cases() as $group) {
        expect(Category::query()->where('group', $group->value)->exists())->toBeTrue();
    }
});
