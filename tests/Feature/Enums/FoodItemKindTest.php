<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\FoodItemKind;

it('exposes a non-empty label, color, icon and description for every case', function (FoodItemKind $kind): void {
    expect($kind->getLabel())->not->toBe('');
    expect($kind->getColor())->not->toBe('');
    expect($kind->getIcon())->not->toBe('');
    expect($kind->getDescription())->not->toBe('');
})->with(FoodItemKind::cases());

it('answers its single-state predicates', function (): void {
    expect(FoodItemKind::Item->isItem())->toBeTrue();
    expect(FoodItemKind::Dish->isDish())->toBeTrue();
    expect(FoodItemKind::Item->isDish())->toBeFalse();
});

it('pairs the composite predicate with its set helper', function (): void {
    expect(FoodItemKind::composite())->toBe([FoodItemKind::Dish]);
    expect(FoodItemKind::Dish->isComposite())->toBeTrue();
    expect(FoodItemKind::Item->isComposite())->toBeFalse();
});

it('pairs the importable predicate with its set helper', function (): void {
    expect(FoodItemKind::importable())->toBe([FoodItemKind::Item]);
    expect(FoodItemKind::Item->isImportable())->toBeTrue();
    expect(FoodItemKind::Dish->isImportable())->toBeFalse();
});

it('orders atomic items ahead of composite dishes', function (): void {
    expect(FoodItemKind::ordered())->toBe([FoodItemKind::Item, FoodItemKind::Dish]);
});
