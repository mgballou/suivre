<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\FoodEntries;

use App\Enums\MealType;
use App\Filament\Resources\FoodEntries\Pages\ListFoodEntries;
use App\Filament\Resources\FoodEntries\Pages\ViewFoodEntry;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->operator = User::factory()->createQuietly();
    $this->meal = Meal::factory()->ofType(MealType::Dinner)->createQuietly(['user_id' => $this->operator->id]);
    $this->actingAs($this->operator);

    // Warm spatie's caches — the process-global permission cache and the acting
    // user's roles relation — so the per-row authorization check doesn't prime
    // them mid-measurement and skew the N+1 query count.
    app(PermissionRegistrar::class)->getPermissions();
    $this->operator->loadMissing('roles');
});

it('lists food entries', function (): void {
    $entries = FoodEntry::factory()->count(3)->forMeal($this->meal)->createQuietly();

    Livewire::test(ListFoodEntries::class)
        ->assertOk()
        ->assertCanSeeTableRecords($entries);
});

it('offers view but never edit — the backstage is read-only', function (): void {
    $entry = FoodEntry::factory()->forMeal($this->meal)->createQuietly();

    Livewire::test(ListFoodEntries::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($entry))
        ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($entry));
});

it('renders the inherited eaten-at, which needs the parent meal eager-loaded', function (): void {
    FoodEntry::factory()->forMeal($this->meal)->createQuietly();

    Livewire::test(ListFoodEntries::class)
        ->assertOk()
        ->assertSee($this->meal->eaten_at->format('M j, Y'));
});

it('filters the table down to entries pending classification', function (): void {
    $pending = FoodEntry::factory()->forMeal($this->meal)->pendingClassification()->createQuietly();
    $classified = FoodEntry::factory()->forMeal($this->meal)
        ->resolvedToFoodItem(FoodItem::factory()->createQuietly(), 'oat milk')
        ->createQuietly();

    Livewire::test(ListFoodEntries::class)
        ->filterTable('food_item_id', false)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$classified]);
});

it('filters the table by the parent meal type', function (): void {
    $breakfast = Meal::factory()->ofType(MealType::Breakfast)->createQuietly(['user_id' => $this->operator->id]);
    $dinnerEntry = FoodEntry::factory()->forMeal($this->meal)->createQuietly();
    $breakfastEntry = FoodEntry::factory()->forMeal($breakfast)->createQuietly();

    Livewire::test(ListFoodEntries::class)
        ->filterTable('meal_type', [MealType::Breakfast->value])
        ->assertCanSeeTableRecords([$breakfastEntry])
        ->assertCanNotSeeTableRecords([$dinnerEntry]);
});

it('renders a food entry in the infolist', function (): void {
    $entry = FoodEntry::factory()->forMeal($this->meal)->createQuietly(['text' => 'kimchi']);

    Livewire::test(ViewFoodEntry::class, ['record' => $entry->getRouteKey()])
        ->assertOk()
        ->assertSee('kimchi')
        ->assertSee($this->operator->name);
});

it('lists food entries without an N+1 on the meal or its owner', function (): void {
    FoodEntry::factory()->forMeal($this->meal)->createQuietly();

    $baseline = countFoodEntryListQueries();

    FoodEntry::factory()->count(5)->forMeal($this->meal)->createQuietly();
    FoodEntry::factory()->count(3)->createQuietly();

    expect(countFoodEntryListQueries())->toBe($baseline);
});

function countFoodEntryListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListFoodEntries::class)->assertOk();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
