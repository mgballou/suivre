<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryGroup;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The curated starter trigger taxonomy (D9/D10).
 *
 * Allergen and ingredient categories mirror what a structured food dataset can
 * auto-derive on import; the research categories are the ones no dataset can
 * supply and must be curated by hand. Idempotent — keyed on `slug`, so it is
 * safe to re-run as the baseline vocabulary evolves.
 */
class CategoryTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }

    /**
     * @return array<int, array{name: string, slug: string, group: CategoryGroup, description: string, research_source: string|null}>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Dairy',
                'slug' => 'dairy',
                'group' => CategoryGroup::Allergen,
                'description' => 'Milk and milk-derived ingredients, including lactose and milk proteins.',
                'research_source' => null,
            ],
            [
                'name' => 'Gluten',
                'slug' => 'gluten',
                'group' => CategoryGroup::Allergen,
                'description' => 'Gluten-containing grains — wheat, barley, rye and their derivatives.',
                'research_source' => null,
            ],
            [
                'name' => 'Nuts',
                'slug' => 'nuts',
                'group' => CategoryGroup::Allergen,
                'description' => 'Tree nuts and peanuts, including nut butters, oils and flours.',
                'research_source' => null,
            ],
            [
                'name' => 'Soy',
                'slug' => 'soy',
                'group' => CategoryGroup::Allergen,
                'description' => 'Soybeans and soy-derived ingredients such as tofu, soy sauce and lecithin.',
                'research_source' => null,
            ],
            [
                'name' => 'Egg',
                'slug' => 'egg',
                'group' => CategoryGroup::Allergen,
                'description' => 'Whole egg, egg white and egg yolk in any prepared form.',
                'research_source' => null,
            ],
            [
                'name' => 'Caffeine',
                'slug' => 'caffeine',
                'group' => CategoryGroup::Ingredient,
                'description' => 'Coffee, tea, cola, energy drinks, chocolate and caffeine additives.',
                'research_source' => null,
            ],
            [
                'name' => 'Added sugar',
                'slug' => 'added-sugar',
                'group' => CategoryGroup::Ingredient,
                'description' => 'Sugars added during processing or preparation, excluding sugars intrinsic to whole foods.',
                'research_source' => null,
            ],
            [
                'name' => 'Alcohol',
                'slug' => 'alcohol',
                'group' => CategoryGroup::Ingredient,
                'description' => 'Alcoholic drinks and alcohol used as a cooking or flavouring ingredient.',
                'research_source' => null,
            ],
            [
                'name' => 'Histamine',
                'slug' => 'histamine',
                'group' => CategoryGroup::Research,
                'description' => 'Histamine-rich and histamine-liberating foods — aged cheese, cured meat, fermented foods, wine.',
                'research_source' => 'Comas-Basté et al., Histamine Intolerance: The Current State of the Art, Biomolecules 2020 — https://doi.org/10.3390/biom10081181',
            ],
            [
                'name' => 'Nightshade',
                'slug' => 'nightshade',
                'group' => CategoryGroup::Research,
                'description' => 'Solanaceae family — tomato, potato, aubergine, peppers and paprika.',
                'research_source' => 'Arthritis Foundation — Nightshade vegetables and arthritis: https://www.arthritis.org/health-wellness/healthy-living/nutrition/healthy-eating/nightshade-vegetables',
            ],
            [
                'name' => 'FODMAP',
                'slug' => 'fodmap',
                'group' => CategoryGroup::Research,
                'description' => 'Fermentable oligo-, di-, monosaccharides and polyols — onion, garlic, wheat, legumes, certain fruits.',
                'research_source' => 'Monash University FODMAP programme — https://www.monashfodmap.com',
            ],
        ];
    }
}
