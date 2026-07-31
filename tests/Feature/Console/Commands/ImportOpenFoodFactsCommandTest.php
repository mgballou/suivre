<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\FoodItem;
use Database\Seeders\CategoryTaxonomySeeder;

beforeEach(function (): void {
    $this->seed(CategoryTaxonomySeeder::class);
});

function samplePath(): string
{
    return base_path('tests/Fixtures/open-food-facts-sample.jsonl');
}

it('imports the export it is pointed at', function (): void {
    $this->artisan('food:import-off', ['path' => samplePath()])
        ->assertSuccessful();

    expect(FoodItem::query()->count())->toBe(13);
});

it('reports what the run did', function (): void {
    $this->artisan('food:import-off', ['path' => samplePath()])
        ->expectsOutputToContain('Created')
        ->expectsOutputToContain('Merged into existing foods')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();
});

it('prints the attribution the licence requires', function (): void {
    $this->artisan('food:import-off', ['path' => samplePath()])
        ->expectsOutputToContain(config()->string('food.catalog.attribution'))
        ->expectsOutputToContain(config()->string('food.catalog.license_url'))
        ->assertSuccessful();
});

it('names the dataset and its licence in the attribution', function (): void {
    $attribution = config()->string('food.catalog.attribution');

    expect($attribution)->toContain('Open Food Facts');
    expect($attribution)->toContain('Open Database License');
    expect(config()->string('food.catalog.license_url'))->toContain('opendatacommons.org');
});

it('passes the subset flags through to the import', function (): void {
    $this->artisan('food:import-off', [
        'path' => samplePath(),
        '--country' => 'united-kingdom',
        '--limit' => '2',
    ])->assertSuccessful();

    expect(FoodItem::query()->count())->toBe(2);
    expect(FoodItem::query()->where('normalized_name', 'chorizo')->exists())->toBeFalse();
});

it('resumes from a line offset', function (): void {
    $this->artisan('food:import-off', ['path' => samplePath(), '--skip' => '2'])
        ->assertSuccessful();

    expect(FoodItem::query()->where('normalized_name', 'whole milk')->exists())->toBeFalse();
});

it('fails with a readable message when the export is missing', function (): void {
    $this->artisan('food:import-off', ['path' => '/nowhere/open-food-facts.jsonl'])
        ->expectsOutputToContain('could not be opened')
        ->assertFailed();

    expect(FoodItem::query()->count())->toBe(0);
});
