<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FoodItemKind;
use App\Models\Category;
use App\Models\FoodItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodItem>
 */
class FoodItemFactory extends Factory
{
    /**
     * Define the model's default state — a hand-curated atomic item.
     *
     * `normalized_name` is intentionally absent: the model derives it from
     * `name`, and letting a factory set it independently would let tests build
     * rows the application cannot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'kind' => FoodItemKind::Item,
            'source' => null,
            'source_ref' => null,
        ];
    }

    /**
     * Pin the catalog entry to a name, and with it its normalized form.
     */
    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * A composite dish whose categories come from curation and components (D9).
     */
    public function dish(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => FoodItemKind::Dish,
        ]);
    }

    /**
     * A row bootstrapped from a dataset import rather than curated (D10).
     */
    public function imported(string $source = 'open_food_facts', ?string $sourceRef = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $source,
            'source_ref' => $sourceRef ?? (string) fake()->unique()->ean13(),
        ]);
    }

    /**
     * Attach curated trigger categories once the food item exists.
     *
     * @param  array<int, Category>  $categories
     */
    public function withCategories(array $categories): static
    {
        return $this->afterCreating(function (FoodItem $foodItem) use ($categories): void {
            $foodItem->categories()->syncWithoutDetaching(
                collect($categories)->pluck('id')->all()
            );
        });
    }

    /**
     * Give the food item synonyms the classifier can resolve against.
     *
     * @param  array<int, string>  $aliases
     */
    public function withAliases(array $aliases): static
    {
        return $this->afterCreating(function (FoodItem $foodItem) use ($aliases): void {
            foreach ($aliases as $alias) {
                FoodItemAliasFactory::new()->for($foodItem)->createQuietly(['alias' => $alias]);
            }
        });
    }

    /**
     * Compose a dish from existing catalog items.
     *
     * @param  array<int, FoodItem>  $components
     */
    public function composedOf(array $components): static
    {
        return $this->dish()->afterCreating(function (FoodItem $foodItem) use ($components): void {
            $foodItem->components()->syncWithoutDetaching(
                collect($components)->pluck('id')->all()
            );
        });
    }
}
