<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Conditions;

use App\Filament\Resources\Conditions\ConditionResource;
use App\Filament\Resources\Conditions\Pages\ListConditions;
use App\Filament\Resources\Conditions\Pages\ViewCondition;
use App\Models\Condition;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->operator = User::factory()->createQuietly();
    $this->actingAs($this->operator);
});

it('lists conditions', function (): void {
    $conditions = Condition::factory()->count(3)->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListConditions::class)
        ->assertOk()
        ->assertCanSeeTableRecords($conditions);
});

it('offers view but never edit — the backstage is read-only', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListConditions::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($condition))
        ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($condition));
});

it('exposes no create or edit page', function (): void {
    expect(array_keys(ConditionResource::getPages()))
        ->toBe(['index', 'view']);
});

it('filters the table by active state', function (): void {
    $active = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);
    $archived = Condition::factory()->inactive()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListConditions::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('renders a condition in the infolist', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ViewCondition::class, ['record' => $condition->getRouteKey()])
        ->assertOk()
        ->assertSee($condition->name)
        ->assertSee($this->operator->name);
});
