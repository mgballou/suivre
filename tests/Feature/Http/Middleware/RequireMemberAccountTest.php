<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\Condition;
use App\Models\User;

it('sends an administrator who reaches for the journal back to the backstage', function (string $route): void {
    $this->actingAs(User::factory()->admin()->createQuietly());

    $this->get($route)->assertRedirect('/admin');
})->with([
    'the calendar' => ['/calendar'],
    'a day' => ['/day/2026-07-31'],
    'insights' => ['/insights'],
    'onboarding' => ['/onboarding/conditions'],
    'condition settings' => ['/settings/conditions'],
    'the profile screen' => ['/settings/profile'],
    'the security screen' => ['/settings/security'],
    'appearance' => ['/settings/appearance'],
    'the root' => ['/'],
]);

it('lets a member through to their own side', function (): void {
    $user = User::factory()->createQuietly();
    Condition::factory()->for($user)->createQuietly();

    $this->actingAs($user);

    $this->get('/calendar')->assertOk();
    $this->get('/settings/profile')->assertOk();
});

it('leaves an administrator able to sign out', function (): void {
    // Logging out is a `web` route like any other. An administrator bounced away
    // from it would be stuck signed in.
    $this->actingAs(User::factory()->admin()->createQuietly());

    $this->post('/logout')->assertRedirect();

    $this->assertGuest();
});

it('leaves a guest to the welcome page', function (): void {
    $this->get('/')->assertOk();
});

it('gives an administrator somewhere to change their own password', function (): void {
    // They are barred from /settings along with the rest of the user app, so
    // without the panel's own profile page there would be no such screen at all.
    $this->actingAs(User::factory()->admin()->createQuietly());

    $this->get(route('filament.admin.auth.profile'))->assertOk();
});

it('still keeps a member out of the backstage', function (): void {
    $this->actingAs(User::factory()->createQuietly());

    $this->get('/admin')->assertForbidden();
});
