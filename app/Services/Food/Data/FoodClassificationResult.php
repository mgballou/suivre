<?php

declare(strict_types=1);

namespace App\Services\Food\Data;

use App\Enums\FoodClassificationOutcome;
use App\Models\Category;
use App\Models\FoodItem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * What `ClassifyFoodEntry` concluded about a piece of free text.
 *
 * `score` is the trigram similarity of the winning candidate — carried even
 * when it drove a `LowConfidence` outcome, so the review queue (SUI-17) and an
 * operator inspecting a match can see *why* the classifier landed where it did
 * (D9's inspectability requirement), not just a bare pass/fail.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class FoodClassificationResult implements Arrayable
{
    /**
     * @param  Collection<int, Category>  $categories
     */
    public function __construct(
        public FoodClassificationOutcome $outcome,
        public ?FoodItem $foodItem,
        public Collection $categories,
        public float $score,
    ) {}

    /**
     * @return array{
     *     outcome: string,
     *     foodItemId: int|null,
     *     categoryIds: array<int, int>,
     *     score: float,
     * }
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'foodItemId' => $this->foodItem?->id,
            'categoryIds' => $this->categories->map(fn (Category $category): int => $category->id)->all(),
            'score' => $this->score,
        ];
    }
}
