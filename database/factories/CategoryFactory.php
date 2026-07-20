<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryGroup;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'group' => fake()->randomElement(CategoryGroup::cases()),
            'description' => fake()->sentence(),
            'research_source' => null,
        ];
    }

    /**
     * Place the category in a specific governance group.
     */
    public function group(CategoryGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => $group,
        ]);
    }

    /**
     * A research-backed category, cited to its source.
     */
    public function research(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => CategoryGroup::Research,
            'research_source' => fake()->url(),
        ]);
    }
}
