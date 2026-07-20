<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodItemAlias>
 */
class FoodItemAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `normalized_alias` is derived by the model from `alias`, so the factory
     * never sets it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'food_item_id' => FoodItem::factory(),
            'alias' => ucfirst(fake()->unique()->word()),
        ];
    }

    /**
     * Pin the synonym to an exact string.
     */
    public function named(string $alias): static
    {
        return $this->state(fn (array $attributes) => [
            'alias' => $alias,
        ]);
    }
}
