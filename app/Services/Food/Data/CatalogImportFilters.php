<?php

declare(strict_types=1);

namespace App\Services\Food\Data;

/**
 * Which slice of an Open Food Facts export a run should take.
 *
 * The full dump is millions of products; staging wants a few thousand of them
 * (SUI-32). `limit` counts products that pass the filters rather than lines
 * read, so `--limit=1000 --country=united-kingdom` yields a thousand British
 * products instead of however few appear in the first thousand lines.
 *
 * `skip` resumes an interrupted run by line, which is safe because every line
 * imports idempotently — overlapping a resume merely re-merges rows.
 */
readonly class CatalogImportFilters
{
    public function __construct(
        public ?string $country = null,
        public ?string $category = null,
        public ?int $limit = null,
        public int $skip = 0,
    ) {}

    public function accepts(OpenFoodFactsProduct $product): bool
    {
        if ($this->country !== null && ! $product->isFromCountry($this->country)) {
            return false;
        }

        return $this->category === null || $product->isInCategory($this->category);
    }
}
