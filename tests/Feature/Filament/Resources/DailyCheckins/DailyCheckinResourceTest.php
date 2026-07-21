<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\DailyCheckins;

use App\Enums\MoodLevel;
use App\Filament\Resources\DailyCheckins\Pages\EditDailyCheckin;
use App\Filament\Resources\DailyCheckins\Pages\ListDailyCheckins;
use App\Filament\Resources\DailyCheckins\Pages\ViewDailyCheckin;
use App\Models\DailyCheckin;
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

it('lists check-ins', function (): void {
    $checkins = DailyCheckin::factory()->count(3)->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListDailyCheckins::class)
        ->assertOk()
        ->assertCanSeeTableRecords($checkins);
});

it('exposes the row actions on the list page', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListDailyCheckins::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($checkin))
        ->assertActionExists(TestAction::make(EditAction::class)->table($checkin));
});

it('filters the table by mood', function (): void {
    $low = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id, 'mood' => MoodLevel::Low]);
    $good = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id, 'mood' => MoodLevel::Good]);

    Livewire::test(ListDailyCheckins::class)
        ->filterTable('mood', [MoodLevel::Low->value])
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$good]);
});

it('filters the table down to check-ins missing a signal', function (): void {
    $complete = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id]);
    $blank = DailyCheckin::factory()->blank()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(ListDailyCheckins::class)
        ->filterTable('incomplete')
        ->assertCanSeeTableRecords([$blank])
        ->assertCanNotSeeTableRecords([$complete]);
});

it('updates a check-in', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(EditDailyCheckin::class, ['record' => $checkin->getRouteKey()])
        ->fillForm([
            'mood' => MoodLevel::Good->value,
            'note' => 'Corrected in the backstage.',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('daily_checkins', [
        'id' => $checkin->id,
        'mood' => MoodLevel::Good->value,
    ]);
});

it('deletes a check-in from the edit page', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly(['user_id' => $this->operator->id]);

    Livewire::test(EditDailyCheckin::class, ['record' => $checkin->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertDatabaseMissing('daily_checkins', ['id' => $checkin->id]);
});

it('renders a check-in in the infolist', function (): void {
    $checkin = DailyCheckin::factory()->createQuietly([
        'user_id' => $this->operator->id,
        'mood' => MoodLevel::Good,
    ]);

    Livewire::test(ViewDailyCheckin::class, ['record' => $checkin->getRouteKey()])
        ->assertOk()
        ->assertSee($this->operator->name)
        ->assertSee(MoodLevel::Good->getLabel());
});
