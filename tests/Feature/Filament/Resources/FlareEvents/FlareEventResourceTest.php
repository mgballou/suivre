<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\FlareEvents;

use App\Enums\FlareIntensity;
use App\Filament\Resources\FlareEvents\Pages\EditFlareEvent;
use App\Filament\Resources\FlareEvents\Pages\ListFlareEvents;
use App\Filament\Resources\FlareEvents\Pages\ViewFlareEvent;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->operator = User::factory()->createQuietly();
    $this->condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);
    $this->actingAs($this->operator);
});

it('lists flare events', function (): void {
    $flares = FlareEvent::factory()->count(3)->forCondition($this->condition)->createQuietly();

    Livewire::test(ListFlareEvents::class)
        ->assertOk()
        ->assertCanSeeTableRecords($flares);
});

it('exposes the row actions on the list page', function (): void {
    $flare = FlareEvent::factory()->forCondition($this->condition)->createQuietly();

    Livewire::test(ListFlareEvents::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($flare))
        ->assertActionExists(TestAction::make(EditAction::class)->table($flare));
});

it('filters the table by intensity', function (): void {
    $severe = FlareEvent::factory()->forCondition($this->condition)->createQuietly(['intensity' => FlareIntensity::Severe]);
    $mild = FlareEvent::factory()->forCondition($this->condition)->createQuietly(['intensity' => FlareIntensity::Mild]);

    Livewire::test(ListFlareEvents::class)
        ->filterTable('intensity', [FlareIntensity::Severe->value])
        ->assertCanSeeTableRecords([$severe])
        ->assertCanNotSeeTableRecords([$mild]);
});

it('filters the table down to clinically significant flares', function (): void {
    $moderate = FlareEvent::factory()->forCondition($this->condition)->createQuietly(['intensity' => FlareIntensity::Moderate]);
    $mild = FlareEvent::factory()->forCondition($this->condition)->createQuietly(['intensity' => FlareIntensity::Mild]);

    Livewire::test(ListFlareEvents::class)
        ->filterTable('significant')
        ->assertCanSeeTableRecords([$moderate])
        ->assertCanNotSeeTableRecords([$mild]);
});

it('bulk deletes flare events from the list page', function (): void {
    $flares = FlareEvent::factory()->count(2)->forCondition($this->condition)->createQuietly();

    Livewire::test(ListFlareEvents::class)
        ->selectTableRecords($flares->modelKeys())
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified();

    foreach ($flares as $flare) {
        $this->assertDatabaseMissing('flare_events', ['id' => $flare->id]);
    }
});

it('updates a flare event', function (): void {
    $flare = FlareEvent::factory()->forCondition($this->condition)->createQuietly(['intensity' => FlareIntensity::Mild]);

    Livewire::test(EditFlareEvent::class, ['record' => $flare->getRouteKey()])
        ->fillForm([
            'intensity' => FlareIntensity::Severe->value,
            'duration_minutes' => 90,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('flare_events', [
        'id' => $flare->id,
        'intensity' => FlareIntensity::Severe->value,
        'duration_minutes' => 90,
    ]);
});

it('renders a flare event in the infolist', function (): void {
    $flare = FlareEvent::factory()->forCondition($this->condition)->createQuietly([
        'intensity' => FlareIntensity::Severe,
    ]);

    Livewire::test(ViewFlareEvent::class, ['record' => $flare->getRouteKey()])
        ->assertOk()
        ->assertSee($this->condition->name)
        ->assertSee(FlareIntensity::Severe->getLabel());
});

it('lists flare events without an N+1 on the user and condition relations', function (): void {
    FlareEvent::factory()->forCondition($this->condition)->createQuietly();

    $baseline = countFlareEventListQueries();

    FlareEvent::factory()->count(5)->forCondition($this->condition)->createQuietly();

    expect(countFlareEventListQueries())->toBe($baseline);
});

function countFlareEventListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListFlareEvents::class)->assertOk();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
