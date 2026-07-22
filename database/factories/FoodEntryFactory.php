<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodEntry>
 */
class FoodEntryFactory extends Factory
{
    /**
     * Define the model's default state — free text awaiting classification.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meal_id' => Meal::factory(),
            'food_item_id' => null,
            'text' => fake()->words(asText: true),
        ];
    }

    /**
     * Pin the entry to a specific meal.
     */
    public function forMeal(Meal $meal): static
    {
        return $this->state(fn (array $attributes) => [
            'meal_id' => $meal->id,
        ]);
    }

    /**
     * Free text the classifier has not resolved yet.
     */
    public function pendingClassification(): static
    {
        return $this->state(fn (array $attributes) => [
            'food_item_id' => null,
            'text' => fake()->words(asText: true),
        ]);
    }

    /**
     * Link the entry to a catalog food item, as the classifier leaves it.
     */
    public function resolvedToFoodItem(FoodItem $foodItem, ?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'food_item_id' => $foodItem->id,
            'text' => $text,
        ]);
    }
}
