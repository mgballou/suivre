<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Insights\Data;

use App\Services\Insights\Data\DayMask;

it('scores two masks that never coincide at zero overlap', function (): void {
    $overlap = DayMask::of(6, [0, 2, 4])->overlap(DayMask::of(6, [1, 3, 5]));

    expect($overlap)->toBe(0.0);
});

it('scores identical masks at full overlap', function (): void {
    $overlap = DayMask::of(6, [0, 2, 4])->overlap(DayMask::of(6, [0, 2, 4]));

    expect($overlap)->toBe(1.0);
});

it('scores partial overlap as the shared share of the days either covers', function (): void {
    $overlap = DayMask::of(4, [0, 1])->overlap(DayMask::of(4, [1, 2]));

    expect($overlap)->toBe(1 / 3);
});

it('rotates forward and wraps at the end of the span', function (): void {
    $rotated = DayMask::of(5, [0, 1])->rotate(2);

    expect($rotated->indexes())->toBe([2, 3]);
    expect($rotated->rotate(3)->indexes())->toBe([0, 1]);
});

it('keeps the number of days it covers when rotated', function (): void {
    $mask = DayMask::of(11, [0, 3, 9, 10]);

    expect($mask->rotate(7)->count())->toBe($mask->count());
});

it('subtracts one mask from another', function (): void {
    $without = DayMask::of(5, [0, 1, 2])->without(DayMask::of(5, [1, 4]));

    expect($without->indexes())->toBe([0, 2]);
});

it('unions two masks', function (): void {
    $union = DayMask::of(5, [0, 1])->union(DayMask::of(5, [1, 4]));

    expect($union->indexes())->toBe([0, 1, 4]);
});

it('inverts to the days it does not cover', function (): void {
    expect(DayMask::of(4, [1, 3])->complement()->indexes())->toBe([0, 2]);
});
