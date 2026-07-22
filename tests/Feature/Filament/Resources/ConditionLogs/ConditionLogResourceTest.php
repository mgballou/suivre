<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\ConditionLogs;

use App\Filament\Resources\ConditionLogs\Pages\ListConditionLogs;
use App\Filament\Resources\ConditionLogs\Pages\ViewConditionLog;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
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

it('lists condition ratings', function (): void {
    $logs = ConditionLog::factory()->count(3)->forCondition($this->condition)->createQuietly();

    Livewire::test(ListConditionLogs::class)
        ->assertOk()
        ->assertCanSeeTableRecords($logs);
});

it('offers view but never edit — the backstage is read-only', function (): void {
    $log = ConditionLog::factory()->forCondition($this->condition)->createQuietly();

    Livewire::test(ListConditionLogs::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($log))
        ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($log));
});

it('filters the table by condition', function (): void {
    $other = Condition::factory()->createQuietly(['user_id' => $this->operator->id]);
    $mine = ConditionLog::factory()->forCondition($this->condition)->createQuietly();
    $theirs = ConditionLog::factory()->forCondition($other)->createQuietly();

    Livewire::test(ListConditionLogs::class)
        ->filterTable('condition', $this->condition->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('filters the table by date range', function (): void {
    $recent = ConditionLog::factory()
        ->forCondition($this->condition)
        ->on(CarbonImmutable::parse('2026-07-10'))
        ->createQuietly();
    $old = ConditionLog::factory()
        ->forCondition($this->condition)
        ->on(CarbonImmutable::parse('2026-01-10'))
        ->createQuietly();

    Livewire::test(ListConditionLogs::class)
        ->filterTable('date', ['from' => '2026-07-01'])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('renders a rating in the infolist', function (): void {
    $log = ConditionLog::factory()->forCondition($this->condition)->createQuietly();

    Livewire::test(ViewConditionLog::class, ['record' => $log->getRouteKey()])
        ->assertOk()
        ->assertSee($this->condition->name)
        ->assertSee($this->operator->name);
});

it('lists ratings without an N+1 on the user and condition relations', function (): void {
    ConditionLog::factory()->forCondition($this->condition)->createQuietly();

    $baseline = countConditionLogListQueries();

    ConditionLog::factory()->count(5)->forCondition($this->condition)->createQuietly();

    expect(countConditionLogListQueries())->toBe($baseline);
});

function countConditionLogListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListConditionLogs::class)->assertOk();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
