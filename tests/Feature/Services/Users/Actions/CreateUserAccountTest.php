<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\Actions\CreateUserAccount;
use Illuminate\Support\Facades\Hash;

function createAccount(UserRole $role = UserRole::Member, string $timezone = 'America/New_York'): User
{
    return app(CreateUserAccount::class)(
        name: 'Bria',
        email: 'bria@example.com',
        password: 'a-chosen-password',
        timezone: $timezone,
        role: $role,
    );
}

it('creates an account that can sign in straight away', function (): void {
    $user = createAccount();

    expect(Hash::check('a-chosen-password', $user->password))->toBeTrue();
    expect($user->email)->toBe('bria@example.com');
});

it('keeps the timezone the operator chose', function (): void {
    // Not the server's, and not the operator's own — the journal is keyed on
    // this account's local day.
    expect(createAccount(timezone: 'Pacific/Auckland')->timezone)->toBe('Pacific/Auckland');
});

it('marks the address verified, since no mail transport could', function (): void {
    expect(createAccount()->email_verified_at)->not->toBeNull();
});

it('puts the account on the role it was asked for', function (UserRole $role): void {
    expect(createAccount($role)->role)->toBe($role);
})->with([
    'a member' => [UserRole::Member],
    'an administrator' => [UserRole::Admin],
]);

it('gives a new member the user app and not the backstage', function (): void {
    expect(createAccount()->isAdmin())->toBeFalse();
});
