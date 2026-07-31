<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_is_resolved_in_the_users_timezone_not_the_servers(): void
    {
        // 23:30 UTC on the 9th is already the 10th in Auckland (UTC+12 in July).
        $this->travelTo(CarbonImmutable::parse('2026-07-09 23:30:00', 'UTC'));

        $user = User::factory()->tracking()->inTimezone('Pacific/Auckland')->create();

        $this->actingAs($user)
            ->get('/calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar')
                ->where('today', '2026-07-10')
            );
    }

    public function test_guests_are_redirected_to_login_from_the_calendar(): void
    {
        $this->get('/calendar')->assertRedirect(route('login'));
    }

    public function test_guests_see_the_welcome_page_on_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('welcome'));
    }

    public function test_authenticated_users_on_root_are_redirected_to_the_calendar(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/')
            ->assertRedirect(route('calendar'));
    }

    public function test_authenticated_users_land_on_the_calendar_shell(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar')
                ->has('today')
            );
    }

    public function test_the_admin_panel_is_mounted_and_independent_of_the_app_shell(): void
    {
        // Filament owns /admin and is untouched by the app-shell routing. The panel
        // is administrator-only, so an admin reaches it. Filament — not our Inertia
        // shell — answers: the panel HTML carries no `data-page` root (that marks an
        // Inertia page).
        $this->get('/admin/login')->assertOk();

        $this->actingAs(User::factory()->tracking()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-page', escape: false);
    }

    public function test_a_semantically_invalid_month_is_rejected(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/calendar/2026-13')
            ->assertNotFound();
    }

    public function test_a_valid_month_renders_the_calendar(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/calendar/2026-07')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar')
                ->where('month', '2026-07')
            );
    }
}
