<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('calendar', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_registration_keeps_the_timezone_the_browser_reported(): void
    {
        // Without this the account starts on UTC and every day boundary in the
        // journal is wrong for anyone who is not — silently, and on a setting
        // they never saw.
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'traveller@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'America/New_York',
        ]);

        $user = User::query()->where('email', 'traveller@example.com')->sole();

        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_registration_falls_back_to_utc_when_no_timezone_is_offered(): void
    {
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'quiet@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('UTC', User::query()->where('email', 'quiet@example.com')->sole()->timezone);
    }

    public function test_registration_rejects_a_timezone_that_is_not_real(): void
    {
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'nowhere@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'Mars/Olympus_Mons',
        ])->assertSessionHasErrors('timezone');

        $this->assertGuest();
    }

    public function test_registration_mints_a_member_never_an_admin(): void
    {
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'member@example.com')->sole();

        $this->assertTrue($user->hasRole(UserRole::Member));
        $this->assertFalse($user->isAdmin());
    }
}
