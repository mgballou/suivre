<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condition>
 */
class ConditionFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(ConditionHue::cases()),
            'icon' => 'heroicon-o-fire',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the condition is archived.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function hue(ConditionHue $hue): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $hue,
        ]);
    }
}
