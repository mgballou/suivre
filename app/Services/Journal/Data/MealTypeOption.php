<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use App\Enums\MealType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One slot on the meal-type picker.
 *
 * Deliberately not a `ScaleOption`: those carry an int because they project the
 * ordinal scales, where the value *is* a position on a ramp. `MealType` is a
 * nominal category and is string-backed, so reusing ScaleOption would mean
 * casting a meaningful string to a meaningless integer.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class MealTypeOption implements Arrayable
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
        ];
    }

    public static function fromMealType(MealType $mealType): self
    {
        return new self(
            value: $mealType->value,
            label: $mealType->getLabel(),
        );
    }
}
