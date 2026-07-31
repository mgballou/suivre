<?php

declare(strict_types=1);

namespace App\Services\Food\Actions;

use App\Enums\CategoryGroup;
use App\Exceptions\Food\CatalogSourceUnreadableException;
use App\Models\Category;
use App\Services\Food\Data\CatalogImportFilters;
use App\Services\Food\Data\CatalogImportSummary;
use App\Services\Food\Data\OpenFoodFactsProduct;
use Closure;
use Illuminate\Support\Str;

/**
 * Bootstraps the global food catalog from an Open Food Facts export (D10).
 *
 * The export is read a line at a time and never held in memory: the full dump
 * is tens of gigabytes of JSONL, so anything that decodes it whole is a design
 * error rather than a slow implementation. `.gz` is streamed through the zlib
 * wrapper, which is how the dump actually arrives.
 *
 * Every line imports idempotently, and that single property carries both of the
 * operational requirements. A re-run merges rather than duplicates, so it is
 * safe to repeat; and an interrupted run resumes by re-reading from any earlier
 * point, so `skip` only needs to be roughly right.
 */
class ImportOpenFoodFacts
{
    /**
     * @param  Closure(OpenFoodFactsProduct): void|null  $onProduct  Called per imported product, for progress reporting.
     */
    public function __invoke(string $path, CatalogImportFilters $filters, ?Closure $onProduct = null): CatalogImportSummary
    {
        $handle = $this->open($path);

        $categoryIds = $this->autoDerivableCategoryIds();

        $created = 0;
        $merged = 0;
        $skipped = 0;
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber <= $filters->skip) {
                    continue;
                }

                if ($filters->limit !== null && $created + $merged >= $filters->limit) {
                    break;
                }

                $product = OpenFoodFactsProduct::fromJsonLine($line);

                if ($product === null || ! $filters->accepts($product)) {
                    $skipped++;

                    continue;
                }

                $foodItem = app(ImportFoodProduct::class)($product, $categoryIds);

                if ($foodItem->wasRecentlyCreated) {
                    $created++;
                } else {
                    $merged++;
                }

                if ($onProduct !== null) {
                    $onProduct($product);
                }
            }
        } finally {
            fclose($handle);
        }

        return new CatalogImportSummary(created: $created, merged: $merged, skipped: $skipped);
    }

    /**
     * The slugs an import is allowed to assign, resolved to ids once.
     *
     * Restricting the query to the auto-derivable groups is the enforcement of
     * D10's split, not an optimisation: a research slug added to a trigger list
     * by mistake would find no id here and be dropped, so the import cannot
     * assert a category only a person is qualified to.
     *
     * @return array<string, int>
     */
    private function autoDerivableCategoryIds(): array
    {
        /** @var array<string, int> $ids */
        $ids = Category::query()
            ->whereIn('group', CategoryGroup::autoDerivable())
            ->pluck('id', 'slug')
            ->all();

        return $ids;
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        throw_if(! is_readable($path), CatalogSourceUnreadableException::make($path));

        $handle = Str::endsWith($path, '.gz')
            ? fopen('compress.zlib://' . $path, 'rb')
            : fopen($path, 'rb');

        throw_if($handle === false, CatalogSourceUnreadableException::make($path));

        return $handle;
    }
}
