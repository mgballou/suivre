<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;

it('has the pg_trgm extension available for trigram queries', function () {
    $installed = DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'pg_trgm'");

    expect($installed?->ok)->toBe(1);
});

it('can run a trigram similarity query', function () {
    $row = DB::selectOne("SELECT similarity('banana', 'bananas') AS score");

    expect((float) $row->score)->toBeGreaterThan(0.0);
});
