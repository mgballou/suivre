<?php

declare(strict_types=1);

namespace App\Services\Insights\Data;

use App\Models\Category;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A trigger category as it appears on a ranked suspect — flattened off the
 * model so the insights payload never drags a Category (and its curation
 * metadata) into the user-facing app.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class SuspectTag implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    /**
     * @return array{id: int, name: string, slug: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }

    public static function fromCategory(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            slug: $category->slug,
        );
    }
}
