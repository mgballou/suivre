<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\StressLevel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('is an ordered integer-backed enum', function () {
    expect(StressLevel::Low->value)->toBe(1);
    expect(StressLevel::Moderate->value)->toBe(2);
    expect(StressLevel::High->value)->toBe(3);
});

it('implements the Filament rendering contracts', function () {
    expect(StressLevel::Low)->toBeInstanceOf(HasLabel::class);
    expect(StressLevel::Low)->toBeInstanceOf(HasColor::class);
    expect(StressLevel::Low)->toBeInstanceOf(HasIcon::class);
});

it('resolves a label for each case', function () {
    expect(StressLevel::Low->getLabel())->toBe('Low');
    expect(StressLevel::Moderate->getLabel())->toBe('Moderate');
    expect(StressLevel::High->getLabel())->toBe('High');
});

it('resolves a color for each case', function () {
    expect(StressLevel::Low->getColor())->toBe('success');
    expect(StressLevel::Moderate->getColor())->toBe('warning');
    expect(StressLevel::High->getColor())->toBe('danger');
});

it('resolves an icon for each case', function () {
    expect(StressLevel::Low->getIcon())->toBe('heroicon-o-check-circle');
    expect(StressLevel::Moderate->getIcon())->toBe('heroicon-o-exclamation-circle');
    expect(StressLevel::High->getIcon())->toBe('heroicon-o-exclamation-triangle');
});

it('exposes predicates for each state', function () {
    expect(StressLevel::Low->isLow())->toBeTrue();
    expect(StressLevel::Moderate->isModerate())->toBeTrue();
    expect(StressLevel::High->isHigh())->toBeTrue();
});

it('flags elevated stress via predicate and set helper', function () {
    expect(StressLevel::Low->isElevated())->toBeFalse();
    expect(StressLevel::Moderate->isElevated())->toBeTrue();
    expect(StressLevel::High->isElevated())->toBeTrue();

    expect(StressLevel::elevated())->toBe([
        StressLevel::Moderate,
        StressLevel::High,
    ]);
});

it('returns its cases in display order', function () {
    expect(StressLevel::ordered())->toBe([
        StressLevel::Low,
        StressLevel::Moderate,
        StressLevel::High,
    ]);
});
