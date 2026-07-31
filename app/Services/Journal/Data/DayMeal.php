<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use App\Models\Category;
use App\Models\FoodEntry;
use App\Models\Meal;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One meal already logged against a day, with what each line resolved to.
 *
 * `time` arrives pre-formatted; the client never derives one, because
 * `new Date()` reads the device timezone rather than the user's.
 *
 * An unmatched line is shown as the text the user typed, tagless and flagged.
 * That is deliberate: a miss is visible rather than hidden, so the user can see
 * that a line contributes nothing to their insights yet.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DayMeal implements Arrayable
{
    /**
     * @param  array<int, DayMealEntry>  $entries
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $time,
        public array $entries,
    ) {}

    /**
     * @return array{id: int, type: string, time: string, entries: array<int, array{id: int, label: string, tags: array<int, string>, matched: bool}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'time' => $this->time,
            'entries' => array_map(
                static fn (DayMealEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
        ];
    }

    /**
     * Requires `entries.foodItem.categories` to be loaded; strict mode throws
     * rather than lazy-loading once per line.
     */
    public static function fromMeal(Meal $meal, string $timezone): self
    {
        return new self(
            id: $meal->id,
            type: $meal->meal_type->getLabel(),
            time: $meal->eaten_at->setTimezone($timezone)->format('H:i'),
            entries: $meal->entries
                ->map(static function (FoodEntry $entry): DayMealEntry {
                    $foodItem = $entry->foodItem;

                    if ($foodItem === null) {
                        return new DayMealEntry(
                            id: $entry->id,
                            label: (string) $entry->text,
                            tags: [],
                            matched: $entry->isClassified(),
                        );
                    }

                    return new DayMealEntry(
                        id: $entry->id,
                        label: $foodItem->name,
                        tags: $foodItem->categories
                            ->map(static fn (Category $category): string => $category->name)
                            ->values()
                            ->all(),
                        matched: $entry->isClassified(),
                    );
                })
                ->values()
                ->all(),
        );
    }
}
