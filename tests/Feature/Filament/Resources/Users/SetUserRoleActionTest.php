<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Actions\SetUserRoleAction;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Condition;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('promotes a self-registered account to administrator', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    $member = User::factory()->createQuietly();

    Livewire::test(ListUsers::class)
        ->callAction(
            TestAction::make(SetUserRoleAction::class)->table($member),
            ['role' => UserRole::Admin->value],
        )
        ->assertHasNoActionErrors();

    expect($member->fresh()?->isAdmin())->toBeTrue();
});

it('will not set a role that does not exist', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(ListUsers::class)
        ->callAction(
            TestAction::make(SetUserRoleAction::class)->table(User::factory()->createQuietly()),
            ['role' => 'superuser'],
        )
        ->assertHasActionErrors(['role']);
});

it('refuses to let an administrator demote themselves', function (): void {
    // Revoking your own admin locks you out of the only surface that could give
    // it back, so the action is not offered on your own row at all.
    $admin = User::factory()->admin()->createQuietly();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make(SetUserRoleAction::class)->table($admin));

    expect($admin->can('setRole', $admin))->toBeFalse();
});

it('keeps the ability out of a member\'s hands', function (): void {
    $member = User::factory()->createQuietly();

    expect($member->can('setRole', User::factory()->createQuietly()))->toBeFalse();
});

it('refuses to strand a journal behind the other side of the app', function (): void {
    // The two roles reach opposite halves. Flipping this account's role would
    // leave everything it logged intact and its owner unable to reach it.
    $admin = User::factory()->admin()->createQuietly();

    $this->actingAs($admin);

    $member = User::factory()->createQuietly();
    Condition::factory()->for($member)->createQuietly();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make(SetUserRoleAction::class)->table($member));

    expect($admin->can('setRole', $member))->toBeFalse();
});
