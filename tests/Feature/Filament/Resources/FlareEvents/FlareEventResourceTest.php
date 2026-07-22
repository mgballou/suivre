<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\FlareEvents;

use App\Enums\FlareIntensity;
use App\Filament\Resources\FlareEvents\Pages\ListFlareEvents;
use App\Filament\Resources\FlareEvents\Pages\ViewFlareEvent;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->operator = User::factory()->createQuietly();
    $this->condition = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);
    $this->actingAs($this->operator);

    // Warm spatie's caches — the process-global permission cache and the acting
    // user's roles relation — so the per-row authorization check doesn't prime
    // them mid-measurement and skew the N+1 query count.
    app(PermissionRegistrar::class)->getPermissions();
    $this->operator->loadMissing('roles');
});

it('lists flare events', function (): void {
    $flares = FlareEvent::factory()->count(3)->forCondition($this->condition)->createQuietly();

    Livewire::test(ListFlareEvents::class)
        ->assertOk()
        ->assertCanSeeTableRecords($flares);
});

it('offers view but never edit — the backstage is read-only', function (): void {
    $flare = FlareEvent::factory()->forCondition($this->condition)->createQuietly();

    Livewire::test(ListFlareEvents::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($flare))
        ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($flare));
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
