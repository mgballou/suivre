<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Users\Actions;

use App\Models\User;
use App\Services\Users\Actions\ResetUserPassword;
use Illuminate\Support\Facades\Hash;

it('sets a password the account can sign in with', function (): void {
    $user = User::factory()->createQuietly();

    app(ResetUserPassword::class)($user, 'a-new-password');

    expect(Hash::check('a-new-password', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('voids a remember-me session issued before the reset', function (): void {
    // Otherwise the cookie outlives the password it was issued against, and
    // whoever held the old session stays signed in after the reset.
    $user = User::factory()->createQuietly();
    $user->setRememberToken('the-old-token');
    $user->save();

    app(ResetUserPassword::class)($user, 'a-new-password');

    expect($user->fresh()?->getRememberToken())->not->toBe('the-old-token');
});

it('leaves everything the account logged alone', function (): void {
    $user = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    app(ResetUserPassword::class)($user, 'a-new-password');

    expect($user->fresh()?->timezone)->toBe('Pacific/Auckland');
    expect($user->fresh()?->email)->toBe($user->email);
});
