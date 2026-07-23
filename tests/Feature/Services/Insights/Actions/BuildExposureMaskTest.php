<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Actions;

use App\Services\Insights\Actions\BuildExposureMask;
use App\Services\Insights\Data\DayMask;

it('marks the occurrence day and the window that follows it', function (): void {
    $presence = DayMask::of(6, [0, 3]);

    $exposed = app(BuildExposureMask::class)($presence, 2);

    expect($exposed->days)->toBe([true, true, true, true, true, true]);
});

it('unions overlapping windows rather than counting a day twice', function (): void {
    $presence = DayMask::of(4, [0, 1]);

    $exposed = app(BuildExposureMask::class)($presence, 1);

    expect($exposed->days)->toBe([true, true, true, false]);
    expect($exposed->count())->toBe(3);
});

it('stops the window at the end of the span', function (): void {
    $presence = DayMask::of(4, [3]);

    $exposed = app(BuildExposureMask::class)($presence, 3);

    expect($exposed->days)->toBe([false, false, false, true]);
});

it('marks only the occurrence day at a window of zero', function (): void {
    $presence = DayMask::of(5, [1, 4]);

    $exposed = app(BuildExposureMask::class)($presence, 0);

    expect($exposed->indexes())->toBe([1, 4]);
});
