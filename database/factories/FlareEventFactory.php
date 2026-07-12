<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FlareIntensity;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlareEvent>
 */
class FlareEventFactory extends Factory
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
            'condition_id' => Condition::factory(),
            'occurred_at' => fake()->dateTimeBetween('-1 month'),
            'intensity' => fake()->randomElement(FlareIntensity::cases()),
            'duration_minutes' => fake()->boolean() ? fake()->numberBetween(15, 480) : null,
            'note' => fake()->boolean() ? fake()->sentence() : null,
        ];
    }

    /**
     * Pin the flare to a specific condition, aligning its owning user.
     */
    public function forCondition(Condition $condition): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_id' => $condition->id,
            'user_id' => $condition->user_id,
        ]);
    }
}
