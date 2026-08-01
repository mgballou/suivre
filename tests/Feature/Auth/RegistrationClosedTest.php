<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;

it('serves no sign-up page', function (): void {
    $this->get('/register')->assertNotFound();
});

it('accepts no sign-up post', function (): void {
    $this->post('/register', [
        'name' => 'Uninvited',
        'email' => 'uninvited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'uninvited@example.com')->exists())->toBeFalse();
});

it('offers no route to a sign-up page from the login screen', function (): void {
    // A dead link here is worse than none: the account it promises cannot be made.
    expect(file_get_contents(resource_path('js/pages/auth/login.tsx')))
        ->not->toContain('register');
});
