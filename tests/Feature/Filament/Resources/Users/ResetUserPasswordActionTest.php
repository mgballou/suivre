<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users;

use App\Filament\Resources\Users\Actions\ResetUserPasswordAction;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets an administrator set a locked-out account a new password', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    $member = User::factory()->createQuietly();

    Livewire::test(ListUsers::class)
        ->callAction(
            TestAction::make(ResetUserPasswordAction::class)->table($member),
            ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'],
        )
        ->assertHasNoActionErrors();

    expect(Hash::check('a-new-password', $member->fresh()?->password ?? ''))->toBeTrue();
});

it('will not set a password the account cannot confirm', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(ListUsers::class)
        ->callAction(
            TestAction::make(ResetUserPasswordAction::class)->table(User::factory()->createQuietly()),
            ['password' => 'a-new-password', 'password_confirmation' => 'something-else'],
        )
        ->assertHasActionErrors(['password']);
});

it('keeps the ability out of a member\'s hands', function (): void {
    $member = User::factory()->createQuietly();

    expect($member->can('resetPassword', User::factory()->createQuietly()))->toBeFalse();
});

it('still refuses to let the backstage edit an account', function (): void {
    // The password exception is narrow on purpose; everything else about an
    // account is still the owner's.
    $admin = User::factory()->admin()->createQuietly();

    expect($admin->can('update', User::factory()->createQuietly()))->toBeFalse();
});
