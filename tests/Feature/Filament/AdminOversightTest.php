<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Conditions\Pages\ViewCondition;
use App\Filament\Resources\DailyCheckins\Pages\ViewDailyCheckin;
use App\Filament\Resources\FlareEvents\Pages\ViewFlareEvent;
use App\Models\Condition;
use App\Models\DailyCheckin;
use App\Models\FlareEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->createQuietly();
    $this->member = User::factory()->createQuietly();
});

it('denies the backstage to an ordinary app user', function (): void {
    expect($this->member->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('grants the backstage to an administrator', function (): void {
    expect($this->admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('lets an administrator open a condition owned by another user', function (): void {
    $condition = Condition::factory()->for($this->member)->createQuietly();

    $this->actingAs($this->admin);

    Livewire::test(ViewCondition::class, ['record' => $condition->getRouteKey()])
        ->assertOk()
        ->assertSee($condition->name);
});

it('lets an administrator open a flare event owned by another user', function (): void {
    $flareEvent = FlareEvent::factory()->for($this->member)->createQuietly();

    $this->actingAs($this->admin);

    Livewire::test(ViewFlareEvent::class, ['record' => $flareEvent->getRouteKey()])
        ->assertOk();
});

it('lets an administrator open a daily check-in owned by another user', function (): void {
    $checkin = DailyCheckin::factory()->for($this->member)->createQuietly();

    $this->actingAs($this->admin);

    Livewire::test(ViewDailyCheckin::class, ['record' => $checkin->getRouteKey()])
        ->assertOk();
});
