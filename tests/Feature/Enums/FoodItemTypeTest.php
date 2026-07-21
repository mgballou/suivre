<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\FoodItemType;

it('exposes a non-empty label, color, icon and description for every case', function (FoodItemType $type): void {
    expect($type->getLabel())->not->toBe('');
    expect($type->getColor())->not->toBe('');
    expect($type->getIcon())->not->toBe('');
    expect($type->getDescription())->not->toBe('');
})->with(FoodItemType::cases());

it('answers its single-state predicates', function (): void {
    expect(FoodItemType::Item->isItem())->toBeTrue();
    expect(FoodItemType::Dish->isDish())->toBeTrue();
    expect(FoodItemType::Item->isDish())->toBeFalse();
});

it('pairs the composite predicate with its set helper', function (): void {
    expect(FoodItemType::composite())->toBe([FoodItemType::Dish]);
    expect(FoodItemType::Dish->isComposite())->toBeTrue();
    expect(FoodItemType::Item->isComposite())->toBeFalse();
});

it('pairs the importable predicate with its set helper', function (): void {
    expect(FoodItemType::importable())->toBe([FoodItemType::Item]);
    expect(FoodItemType::Item->isImportable())->toBeTrue();
    expect(FoodItemType::Dish->isImportable())->toBeFalse();
});

it('orders atomic items ahead of composite dishes', function (): void {
    expect(FoodItemType::ordered())->toBe([FoodItemType::Item, FoodItemType::Dish]);
});
