<?php

declare(strict_types=1);

namespace App\Services\Meals\Data;

use App\Enums\FoodClassificationOutcome;
use App\Models\Category;
use App\Services\Food\Data\FoodClassificationResult;
use Illuminate\Contracts\Support\Arrayable;

/**
 * What the classifier suggests for one typed line, shown back to the user
 * before the meal is saved (D9 — classify, then confirm).
 *
 * The tags are the matched catalog item's, never the line's own: the curated
 * catalog is the single source of trigger tags, which is what lets the
 * correlation engine read them through `foodItem.categories`.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ClassifiedLine implements Arrayable
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $text,
        public ?int $foodItemId,
        public ?string $foodItemName,
        public array $tags,
        public ?float $score,
        public FoodClassificationOutcome $outcome,
    ) {}

    /**
     * @return array{text: string, foodItemId: int|null, foodItemName: string|null, tags: array<int, string>, score: float|null, matched: bool}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'foodItemId' => $this->foodItemId,
            'foodItemName' => $this->foodItemName,
            'tags' => $this->tags,
            'score' => $this->score,
            'matched' => $this->outcome->isMatched(),
        ];
    }

    public static function fromResult(string $text, FoodClassificationResult $result): self
    {
        $foodItem = $result->foodItem;

        return new self(
            text: $text,
            foodItemId: $foodItem?->id,
            foodItemName: $foodItem?->name,
            tags: $result->categories
                ->map(static fn (Category $category): string => $category->name)
                ->values()
                ->all(),
            score: $result->outcome->isMatched() ? $result->score : null,
            outcome: $result->outcome,
        );
    }
}
