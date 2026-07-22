<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Meal;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    // The backstage is administrator-only, so the acting user holds the admin role.
    $this->operator = User::factory()->admin()->createQuietly();
    $this->actingAs($this->operator);

    // Warm spatie's caches — the process-global permission cache and the acting
    // user's roles relation — so the per-row authorization check doesn't prime
    // them mid-measurement and skew the N+1 query count.
    app(PermissionRegistrar::class)->getPermissions();
    $this->operator->loadMissing('roles');
});

it('lists users', function (): void {
    $users = User::factory()->count(3)->createQuietly();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users->push($this->operator));
});

it('offers view but never edit — the backstage is read-only', function (): void {
    Livewire::test(ListUsers::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($this->operator))
        ->assertActionDoesNotExist(TestAction::make(EditAction::class)->table($this->operator));
});

it('filters the table by email verification', function (): void {
    $unverified = User::factory()->unverified()->createQuietly();

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$unverified])
        ->assertCanNotSeeTableRecords([$this->operator]);
});

it('renders a user in the infolist', function (): void {
    Livewire::test(ViewUser::class, ['record' => $this->operator->getRouteKey()])
        ->assertOk()
        ->assertSee($this->operator->name)
        ->assertSee($this->operator->email);
});

it('counts journal records without an N+1', function (): void {
    Meal::factory()->count(2)->createQuietly(['user_id' => $this->operator->id]);

    $queriesForOne = countListQueries();

    User::factory()->count(4)->createQuietly()->each(
        fn (User $user) => Meal::factory()->count(2)->createQuietly(['user_id' => $user->id]),
    );

    expect(countListQueries())->toBe($queriesForOne);
});

function countListQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ListUsers::class)->assertOk();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}
