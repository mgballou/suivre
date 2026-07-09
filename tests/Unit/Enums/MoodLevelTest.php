<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\MoodLevel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('is an ordered integer-backed enum', function () {
    expect(MoodLevel::Low->value)->toBe(1);
    expect(MoodLevel::Neutral->value)->toBe(2);
    expect(MoodLevel::Good->value)->toBe(3);
});

it('implements the Filament rendering contracts', function () {
    expect(MoodLevel::Low)->toBeInstanceOf(HasLabel::class);
    expect(MoodLevel::Low)->toBeInstanceOf(HasColor::class);
    expect(MoodLevel::Low)->toBeInstanceOf(HasIcon::class);
});

it('resolves a label for each case', function () {
    expect(MoodLevel::Low->getLabel())->toBe('Low');
    expect(MoodLevel::Neutral->getLabel())->toBe('Neutral');
    expect(MoodLevel::Good->getLabel())->toBe('Good');
});

it('resolves a color for each case', function () {
    expect(MoodLevel::Low->getColor())->toBe('danger');
    expect(MoodLevel::Neutral->getColor())->toBe('warning');
    expect(MoodLevel::Good->getColor())->toBe('success');
});

it('resolves an icon for each case', function () {
    expect(MoodLevel::Low->getIcon())->toBe('heroicon-o-face-frown');
    expect(MoodLevel::Neutral->getIcon())->toBe('heroicon-o-minus-circle');
    expect(MoodLevel::Good->getIcon())->toBe('heroicon-o-face-smile');
});

it('exposes predicates for each state', function () {
    expect(MoodLevel::Low->isLow())->toBeTrue();
    expect(MoodLevel::Neutral->isLow())->toBeFalse();

    expect(MoodLevel::Neutral->isNeutral())->toBeTrue();
    expect(MoodLevel::Good->isNeutral())->toBeFalse();

    expect(MoodLevel::Good->isGood())->toBeTrue();
    expect(MoodLevel::Low->isGood())->toBeFalse();
});

it('returns its cases in display order', function () {
    expect(MoodLevel::ordered())->toBe([
        MoodLevel::Low,
        MoodLevel::Neutral,
        MoodLevel::Good,
    ]);
});
