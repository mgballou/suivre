<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Food\CatalogSourceUnreadableException;
use App\Services\Food\Actions\ImportOpenFoodFacts;
use App\Services\Food\Data\CatalogImportFilters;
use Illuminate\Console\Command;

class ImportOpenFoodFactsCommand extends Command
{
    /**
     * How often a long run reports progress. The full dump takes hours; a
     * command that prints nothing until it finishes is indistinguishable from
     * one that has hung.
     */
    private const int PROGRESS_INTERVAL = 1000;

    /** @var string */
    protected $signature = 'food:import-off
        {path : Path to an Open Food Facts JSONL export (.jsonl or .jsonl.gz)}
        {--country= : Only import products sold in this country, e.g. united-kingdom}
        {--category= : Only import products in this Open Food Facts category, e.g. beverages}
        {--limit= : Stop after this many products reach the catalog}
        {--skip=0 : Skip this many lines first, to resume an interrupted run}';

    /** @var string */
    protected $description = 'Bootstrap the food catalog from an Open Food Facts export, deriving allergen and ingredient categories';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        $imported = 0;

        try {
            $summary = app(ImportOpenFoodFacts::class)(
                path: $path,
                filters: new CatalogImportFilters(
                    country: $this->stringOption('country'),
                    category: $this->stringOption('category'),
                    limit: $this->intOption('limit'),
                    skip: $this->intOption('skip') ?? 0,
                ),
                onProduct: function () use (&$imported): void {
                    $imported++;

                    if ($imported % self::PROGRESS_INTERVAL === 0) {
                        $this->components->task("imported {$imported} products");
                    }
                },
            );
        } catch (CatalogSourceUnreadableException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Created', (string) $summary->created);
        $this->components->twoColumnDetail('Merged into existing foods', (string) $summary->merged);
        $this->components->twoColumnDetail('Skipped', (string) $summary->skipped);

        // Written plainly rather than through a component: the licence text has
        // to survive verbatim, and the styled helpers wrap it to the terminal.
        $this->newLine();
        $this->line(config()->string('food.catalog.attribution'));
        $this->line(config()->string('food.catalog.license_url'));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intOption(string $name): ?int
    {
        $value = $this->stringOption($name);

        return $value === null ? null : (int) $value;
    }
}
