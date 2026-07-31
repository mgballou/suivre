<?php

declare(strict_types=1);

namespace App\Services\Food\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What one catalog import run did.
 *
 * `merged` is the interesting number. The catalog dedups on normalized name, so
 * a second barcode for the same food folds its derived categories into the row
 * that already exists rather than creating a near-duplicate. A high merge count
 * on a first run means the export carries many variants of the same product; a
 * run that is entirely merges is a re-run, and proves the import is idempotent.
 *
 * @implements Arrayable<string, int>
 */
readonly class CatalogImportSummary implements Arrayable
{
    public function __construct(
        public int $created,
        public int $merged,
        public int $skipped,
    ) {}

    /**
     * Products that reached the catalog, whether as a new row or folded into one.
     */
    public function imported(): int
    {
        return $this->created + $this->merged;
    }

    /**
     * @return array{created: int, merged: int, skipped: int, imported: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'merged' => $this->merged,
            'skipped' => $this->skipped,
            'imported' => $this->imported(),
        ];
    }
}
