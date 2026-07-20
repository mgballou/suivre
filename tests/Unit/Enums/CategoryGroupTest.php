<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CategoryGroup;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

it('is a string-backed enum keyed on stable slugs', function () {
    expect(CategoryGroup::Allergen->value)->toBe('allergen');
    expect(CategoryGroup::Ingredient->value)->toBe('ingredient');
    expect(CategoryGroup::Research->value)->toBe('research');
});

it('implements the Filament rendering contracts', function () {
    expect(CategoryGroup::Allergen)->toBeInstanceOf(HasLabel::class);
    expect(CategoryGroup::Allergen)->toBeInstanceOf(HasColor::class);
    expect(CategoryGroup::Allergen)->toBeInstanceOf(HasIcon::class);
    expect(CategoryGroup::Allergen)->toBeInstanceOf(HasDescription::class);
});

it('resolves a label for each case', function () {
    expect(CategoryGroup::Allergen->getLabel())->toBe('Allergen');
    expect(CategoryGroup::Ingredient->getLabel())->toBe('Ingredient / additive');
    expect(CategoryGroup::Research->getLabel())->toBe('Research');
});

it('resolves a color for each case', function () {
    expect(CategoryGroup::Allergen->getColor())->toBe('danger');
    expect(CategoryGroup::Ingredient->getColor())->toBe('warning');
    expect(CategoryGroup::Research->getColor())->toBe('info');
});

it('resolves an icon for each case', function () {
    expect(CategoryGroup::Allergen->getIcon())->toBe('heroicon-o-exclamation-triangle');
    expect(CategoryGroup::Ingredient->getIcon())->toBe('heroicon-o-beaker');
    expect(CategoryGroup::Research->getIcon())->toBe('heroicon-o-academic-cap');
});

it('resolves a description for each case', function () {
    foreach (CategoryGroup::cases() as $group) {
        expect($group->getDescription())->not->toBeEmpty();
    }
});

it('exposes predicates for each state', function () {
    expect(CategoryGroup::Allergen->isAllergen())->toBeTrue();
    expect(CategoryGroup::Ingredient->isIngredient())->toBeTrue();
    expect(CategoryGroup::Research->isResearch())->toBeTrue();

    expect(CategoryGroup::Research->isAllergen())->toBeFalse();
    expect(CategoryGroup::Allergen->isResearch())->toBeFalse();
});

it('flags auto-derivable groups via predicate and set helper', function () {
    expect(CategoryGroup::Allergen->isAutoDerivable())->toBeTrue();
    expect(CategoryGroup::Ingredient->isAutoDerivable())->toBeTrue();
    expect(CategoryGroup::Research->isAutoDerivable())->toBeFalse();

    expect(CategoryGroup::autoDerivable())->toBe([
        CategoryGroup::Allergen,
        CategoryGroup::Ingredient,
    ]);
});

it('flags curated-only groups via predicate and set helper', function () {
    expect(CategoryGroup::Research->isCuratedOnly())->toBeTrue();
    expect(CategoryGroup::Allergen->isCuratedOnly())->toBeFalse();
    expect(CategoryGroup::Ingredient->isCuratedOnly())->toBeFalse();

    expect(CategoryGroup::curatedOnly())->toBe([
        CategoryGroup::Research,
    ]);
});

it('partitions every case into exactly one of the two set helpers', function () {
    foreach (CategoryGroup::cases() as $group) {
        expect($group->isAutoDerivable())->not->toBe($group->isCuratedOnly());
    }
});

it('returns its cases in display order', function () {
    expect(CategoryGroup::ordered())->toBe([
        CategoryGroup::Allergen,
        CategoryGroup::Ingredient,
        CategoryGroup::Research,
    ]);
});
