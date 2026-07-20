<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\MealType;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('is a string-backed enum keyed by its slot of the day', function () {
    expect(MealType::Breakfast->value)->toBe('breakfast');
    expect(MealType::Lunch->value)->toBe('lunch');
    expect(MealType::Dinner->value)->toBe('dinner');
    expect(MealType::Snack->value)->toBe('snack');
});

it('implements the Filament rendering contracts', function () {
    expect(MealType::Breakfast)->toBeInstanceOf(HasLabel::class);
    expect(MealType::Breakfast)->toBeInstanceOf(HasColor::class);
    expect(MealType::Breakfast)->toBeInstanceOf(HasIcon::class);
});

it('exposes a non-empty label, color and icon for every case', function (MealType $mealType) {
    expect($mealType->getLabel())->not->toBe('');
    expect($mealType->getColor())->not->toBe('');
    expect($mealType->getIcon())->not->toBe('');
})->with(MealType::cases());

it('resolves a label for each case', function () {
    expect(MealType::Breakfast->getLabel())->toBe('Breakfast');
    expect(MealType::Lunch->getLabel())->toBe('Lunch');
    expect(MealType::Dinner->getLabel())->toBe('Dinner');
    expect(MealType::Snack->getLabel())->toBe('Snack');
});

it('answers its single-state predicates', function () {
    expect(MealType::Breakfast->isBreakfast())->toBeTrue();
    expect(MealType::Lunch->isLunch())->toBeTrue();
    expect(MealType::Dinner->isDinner())->toBeTrue();
    expect(MealType::Snack->isSnack())->toBeTrue();
    expect(MealType::Snack->isBreakfast())->toBeFalse();
});

it('pairs the main-meal predicate with its set helper', function () {
    expect(MealType::mainMeals())->toBe([
        MealType::Breakfast,
        MealType::Lunch,
        MealType::Dinner,
    ]);

    expect(MealType::Breakfast->isMainMeal())->toBeTrue();
    expect(MealType::Lunch->isMainMeal())->toBeTrue();
    expect(MealType::Dinner->isMainMeal())->toBeTrue();
    expect(MealType::Snack->isMainMeal())->toBeFalse();
});

it('returns its cases in display order', function () {
    expect(MealType::ordered())->toBe([
        MealType::Breakfast,
        MealType::Lunch,
        MealType::Dinner,
        MealType::Snack,
    ]);
});
