<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\SleepQuality;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('is a binary integer-backed enum', function () {
    expect(SleepQuality::Poor->value)->toBe(1);
    expect(SleepQuality::Good->value)->toBe(2);
    expect(SleepQuality::cases())->toHaveCount(2);
});

it('implements the Filament rendering contracts', function () {
    expect(SleepQuality::Poor)->toBeInstanceOf(HasLabel::class);
    expect(SleepQuality::Poor)->toBeInstanceOf(HasColor::class);
    expect(SleepQuality::Poor)->toBeInstanceOf(HasIcon::class);
});

it('resolves a label for each case', function () {
    expect(SleepQuality::Poor->getLabel())->toBe('Poor');
    expect(SleepQuality::Good->getLabel())->toBe('Good');
});

it('resolves a color for each case', function () {
    expect(SleepQuality::Poor->getColor())->toBe('danger');
    expect(SleepQuality::Good->getColor())->toBe('success');
});

it('resolves an icon for each case', function () {
    expect(SleepQuality::Poor->getIcon())->toBe('heroicon-o-cloud');
    expect(SleepQuality::Good->getIcon())->toBe('heroicon-o-moon');
});

it('exposes predicates for each state', function () {
    expect(SleepQuality::Poor->isPoor())->toBeTrue();
    expect(SleepQuality::Good->isPoor())->toBeFalse();

    expect(SleepQuality::Good->isGood())->toBeTrue();
    expect(SleepQuality::Poor->isGood())->toBeFalse();
});

it('returns its cases in display order', function () {
    expect(SleepQuality::ordered())->toBe([
        SleepQuality::Poor,
        SleepQuality::Good,
    ]);
});
