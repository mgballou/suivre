<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Meals;

use App\Enums\MealType;
use App\Filament\Resources\Meals\Pages\EditMeal;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Meals\Pages\ViewMeal;
use App\Models\FoodEntry;
use App\Models\Meal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->operator = User::factory()->inTimezone('Europe/London')->createQuietly();
    $this->actingAs($this->operator);
});

it('lists meals', function (): void {
    $meals = Meal::factory()->count(3)->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListMeals::class)
        ->assertOk()
        ->assertCanSeeTableRecords($meals);
});

it('exposes the row actions on the list page', function (): void {
    $meal = Meal::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListMeals::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($meal))
        ->assertActionExists(TestAction::make(EditAction::class)->table($meal));
});

it('renders the derived local day, which needs the owner eager-loaded', function (): void {
    $meal = Meal::factory()
        ->eatenAt(CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC'))
        ->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListMeals::class)
        ->assertOk()
        ->assertSee($meal->refresh()->loadMissing('user')->date?->format('M j, Y'));
});

it('filters the table by meal type', function (): void {
    $breakfast = Meal::factory()->ofType(MealType::Breakfast)->createQuietly(['user_id' => $this->operator->id]);
    $dinner = Meal::factory()->ofType(MealType::Dinner)->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListMeals::class)
        ->filterTable('meal_type', [MealType::Breakfast->value])
        ->assertCanSeeTableRecords([$breakfast])
        ->assertCanNotSeeTableRecords([$dinner]);
});

it('filters the table down to meals with no food entries', function (): void {
    $empty = Meal::factory()->createQuietly(['user_id' => $this->operator->id]);
    $filled = Meal::factory()->createQuietly(['user_id' => $this->operator->id]);
    FoodEntry::factory()->forMeal($filled)->createQuietly();

    Livewire::test(ListMeals::class)
        ->filterTable('empty')
        ->assertCanSeeTableRecords([$empty])
        ->assertCanNotSeeTableRecords([$filled]);
});

it('updates a meal', function (): void {
    $meal = Meal::factory()->ofType(MealType::Snack)->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(EditMeal::class, ['record' => $meal->getRouteKey()])
        ->fillForm(['meal_type' => MealType::Dinner->value])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('meals', [
        'id' => $meal->id,
        'meal_type' => MealType::Dinner->value,
    ]);
});

it('renders a meal and its entries in the infolist', function (): void {
    $meal = Meal::factory()->ofType(MealType::Lunch)->createQuietly(['user_id' => $this->operator->id]);
    $entry = FoodEntry::factory()->forMeal($meal)->createQuietly(['text' => 'sourdough toast']);

    Livewire::test(ViewMeal::class, ['record' => $meal->getRouteKey()])
        ->assertOk()
        ->assertSee(MealType::Lunch->getLabel())
        ->assertSee($entry->text);
});

it('lists meals without an N+1 on the owner or entry count', function (): void {
    Meal::factory()->createQuietly(['user_id' => $this->operator->id]);

    $baseline = countMealListQueries();

    Meal::factory()->count(5)->createQuietly(['user_id' => $this->operator->id]);
    Meal::factory()->count(3)->createQuietly();

    expect(countMealListQueries())->toBe($baseline);
});

function countMealListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListMeals::class)->assertOk();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
