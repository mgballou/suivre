<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meal>
 */
class MealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'eaten_at' => fake()->dateTimeBetween('-1 month'),
            'meal_type' => fake()->randomElement(MealType::cases()),
        ];
    }

    /**
     * Pin the meal to a slot of the day.
     */
    public function ofType(MealType $mealType): static
    {
        return $this->state(fn (array $attributes) => [
            'meal_type' => $mealType,
        ]);
    }

    /**
     * Pin the meal to the instant it was eaten.
     */
    public function eatenAt(CarbonImmutable $eatenAt): static
    {
        return $this->state(fn (array $attributes) => [
            'eaten_at' => $eatenAt,
        ]);
    }
}
