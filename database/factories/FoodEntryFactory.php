<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoodEntry;
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
     *
     * Takes a raw key rather than a FoodItem model because the `food_items`
     * catalog arrives with SUI-14; swap the signature for the model then.
     */
    public function resolvedToFoodItem(int $foodItemId, ?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'food_item_id' => $foodItemId,
            'text' => $text,
        ]);
    }
}
