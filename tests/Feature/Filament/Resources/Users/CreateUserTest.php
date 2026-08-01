<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function accountForm(array $overrides = []): array
{
    return [
        'name' => 'Bria',
        'email' => 'bria@example.com',
        'password' => 'a-chosen-password',
        'password_confirmation' => 'a-chosen-password',
        'timezone' => 'America/New_York',
        'role' => UserRole::Member->value,
        ...$overrides,
    ];
}

it('creates the account an administrator fills in', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(CreateUser::class)
        ->fillForm(accountForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'bria@example.com')->sole();

    expect(Hash::check('a-chosen-password', $user->password))->toBeTrue();
    expect($user->timezone)->toBe('America/New_York');
    expect($user->isAdmin())->toBeFalse();
});

it('requires a timezone rather than quietly picking one', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(CreateUser::class)
        ->fillForm(accountForm(['timezone' => '']))
        ->call('create')
        ->assertHasFormErrors(['timezone']);
});

it('will not create a second account on one address', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    User::factory()->createQuietly(['email' => 'bria@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm(accountForm())
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('will not set a password the account cannot confirm', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(CreateUser::class)
        ->fillForm(accountForm(['password_confirmation' => 'something-else']))
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('can mint another administrator', function (): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    Livewire::test(CreateUser::class)
        ->fillForm(accountForm(['role' => UserRole::Admin->value]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'bria@example.com')->sole()->isAdmin())->toBeTrue();
});

it('keeps account creation away from a member', function (): void {
    expect(User::factory()->createQuietly()->can('create', User::class))->toBeFalse();
});
