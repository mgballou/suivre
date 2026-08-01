<?php

declare(strict_types=1);

namespace Tests\Feature\Docker;

use Database\Seeders\CategoryTaxonomySeeder;
use Database\Seeders\CommonFoodsSeeder;

/**
 * The web container seeds on boot, and nothing else does. A dropped line here
 * fails silently and remotely: the app still serves, but every food a user types
 * misses the classifier and no correlation ever has a tag to work with.
 */
function seedCommandPosition(string $seeder): int
{
    $entrypoint = (string) file_get_contents(base_path('docker/entrypoint.sh'));

    $position = strpos($entrypoint, 'db:seed --class=' . class_basename($seeder) . ' --force');

    expect($position)->not->toBeFalse("The entrypoint does not seed {$seeder} on boot.");

    return (int) $position;
}

it('seeds the catalog on boot', function (string $seeder): void {
    seedCommandPosition($seeder);
})->with([
    'taxonomy' => [CategoryTaxonomySeeder::class],
    'foods' => [CommonFoodsSeeder::class],
]);

it('seeds the taxonomy before the foods that resolve against it', function (): void {
    expect(seedCommandPosition(CategoryTaxonomySeeder::class))
        ->toBeLessThan(seedCommandPosition(CommonFoodsSeeder::class));
});
