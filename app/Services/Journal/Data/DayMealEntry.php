<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One line of a logged meal.
 *
 * `matched` is carried rather than inferred from an empty `tags` array: a
 * catalog item can legitimately carry no trigger categories, which is a
 * different thing from never having been matched at all.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DayMealEntry implements Arrayable
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public int $id,
        public string $label,
        public array $tags,
        public bool $matched,
    ) {}

    /**
     * @return array{id: int, label: string, tags: array<int, string>, matched: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'tags' => $this->tags,
            'matched' => $this->matched,
        ];
    }
}
