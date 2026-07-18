<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConditionLog>
 */
class ConditionLogFactory extends Factory
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
            'date' => fake()->unique()->date(),
            'intensity' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Pin the rating to a specific condition, aligning its owning user.
     */
    public function forCondition(Condition $condition): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_id' => $condition->id,
            'user_id' => $condition->user_id,
        ]);
    }

    /**
     * Pin the rating to the user's local calendar day it belongs to.
     */
    public function on(CarbonImmutable $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
