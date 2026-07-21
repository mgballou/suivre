<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Conditions;

use App\Filament\Resources\Conditions\Pages\CreateCondition;
use App\Filament\Resources\Conditions\Pages\EditCondition;
use App\Filament\Resources\Conditions\Pages\ListConditions;
use App\Filament\Resources\Conditions\Pages\ViewCondition;
use App\Models\Condition;
use App\Models\User;
use Filament\Actions\DeleteAction;
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

it('exposes the row actions on the list page', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListConditions::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($condition))
        ->assertActionExists(TestAction::make(EditAction::class)->table($condition));
});

it('filters the table by active state', function (): void {
    $active = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);
    $archived = Condition::factory()->inactive()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListConditions::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('creates a condition', function (): void {
    Livewire::test(CreateCondition::class)
        ->fillForm([
            'user_id' => $this->operator->id,
            'name' => 'Eczema',
            'color' => '#ff0000',
            'icon' => 'heroicon-o-fire',
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('conditions', [
        'user_id' => $this->operator->id,
        'name' => 'Eczema',
    ]);
});

it('validates the create form', function (array $data, array $errors): void {
    Livewire::test(CreateCondition::class)
        ->fillForm([
            'user_id' => $this->operator->id,
            'name' => 'Eczema',
            'color' => '#ff0000',
            'icon' => 'heroicon-o-fire',
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`user_id` is required' => [['user_id' => null], ['user_id' => 'required']],
    '`icon` is required' => [['icon' => null], ['icon' => 'required']],
]);

it('updates a condition', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(EditCondition::class, ['record' => $condition->getRouteKey()])
        ->fillForm(['name' => 'Rosacea'])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('conditions', [
        'id' => $condition->id,
        'name' => 'Rosacea',
    ]);
});

it('deletes a condition from the edit page', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(EditCondition::class, ['record' => $condition->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertDatabaseMissing('conditions', ['id' => $condition->id]);
});

it('renders a condition in the infolist', function (): void {
    $condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ViewCondition::class, ['record' => $condition->getRouteKey()])
        ->assertOk()
        ->assertSee($condition->name)
        ->assertSee($this->operator->name);
});
