<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyCheckin>
 */
class DailyCheckinFactory extends Factory
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
            'date' => fake()->unique()->date(),
            'sleep' => fake()->randomElement(SleepQuality::cases()),
            'mood' => fake()->randomElement(MoodLevel::cases()),
            'stress' => fake()->randomElement(StressLevel::cases()),
            'note' => fake()->boolean() ? fake()->sentence() : null,
        ];
    }

    /**
     * Indicate the user's local calendar day the check-in belongs to.
     */
    public function on(CarbonImmutable $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    /**
     * Indicate that the day was logged without any of its signals recorded.
     */
    public function blank(): static
    {
        return $this->state(fn (array $attributes) => [
            'sleep' => null,
            'mood' => null,
            'stress' => null,
            'note' => null,
        ]);
    }
}
