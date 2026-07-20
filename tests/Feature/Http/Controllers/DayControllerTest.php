<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_an_unlogged_day_as_an_empty_check_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('day')
                ->where('date', '2026-07-15')
                ->where('label', 'Wednesday 15 July 2026')
                ->where('month', '2026-07')
                ->where('level', 0)
                ->where('checkin.mood', null)
                ->where('checkin.sleep', null)
                ->where('checkin.stress', null)
                ->where('checkin.note', null)
                ->etc()
            );
    }

    public function test_it_renders_a_saved_check_in(): void
    {
        $user = User::factory()->create();

        DailyCheckin::factory()
            ->for($user)
            ->on(CarbonImmutable::parse('2026-07-15'))
            ->createQuietly([
                'mood' => MoodLevel::Good,
                'sleep' => SleepQuality::Poor,
                'stress' => StressLevel::High,
                'note' => 'slept badly',
            ]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('level', 1)
                ->where('checkin.mood', MoodLevel::Good->value)
                ->where('checkin.sleep', SleepQuality::Poor->value)
                ->where('checkin.stress', StressLevel::High->value)
                ->where('checkin.note', 'slept badly')
                ->etc()
            );
    }

    public function test_it_offers_each_scales_cases_in_their_declared_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('scales.mood', 3)
                ->has('scales.sleep', 2)
                ->has('scales.stress', 3)
                ->where('scales.mood.0.value', MoodLevel::Low->value)
                ->where('scales.mood.0.label', 'Low')
                ->where('scales.mood.2.value', MoodLevel::Good->value)
                ->where('scales.stress.2.label', 'High')
                ->etc()
            );
    }

    public function test_it_does_not_show_another_users_check_in(): void
    {
        DailyCheckin::factory()
            ->for(User::factory()->create())
            ->on(CarbonImmutable::parse('2026-07-15'))
            ->createQuietly(['mood' => MoodLevel::Good]);

        $this->actingAs(User::factory()->create())
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('level', 0)
                ->where('checkin.mood', null)
                ->etc()
            );
    }

    public function test_it_marks_the_day_as_today_in_the_users_timezone_not_the_servers(): void
    {
        // 23:30 UTC on the 15th is already the 16th in Auckland (UTC+12 in July).
        $this->travelTo(CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC'));

        $user = User::factory()->inTimezone('Pacific/Auckland')->create();

        $this->actingAs($user)
            ->get('/day/2026-07-16')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('isToday', true)->etc());

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('isToday', false)->etc());
    }

    public function test_it_rejects_a_date_that_matches_the_pattern_but_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/day/2026-02-31')
            ->assertNotFound();
    }

    public function test_guests_cannot_reach_a_day(): void
    {
        $this->get('/day/2026-07-15')->assertRedirect(route('login'));
    }
}
