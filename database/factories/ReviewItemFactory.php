<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\FoodEntry;
use App\Models\ReviewItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ReviewItem>
 */
class ReviewItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reviewable_type' => (new FoodEntry())->getMorphClass(),
            'reviewable_id' => FoodEntry::factory(),
            'text' => fake()->words(asText: true),
            'score' => fake()->randomFloat(3, 0, 0.4),
            'status' => ReviewStatus::Pending,
        ];
    }

    /**
     * Point the item at any reviewable record, resolving its morph string
     * through `getMorphClass()` rather than hard-coding one.
     */
    public function reviewable(Model $model): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewable_type' => $model->getMorphClass(),
            'reviewable_id' => $model->getKey(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Resolved,
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Dismissed,
        ]);
    }
}
