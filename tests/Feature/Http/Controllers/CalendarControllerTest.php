<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\DailyCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_users_current_month_when_none_is_requested(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC'));

        $this->actingAs(User::factory()->create())
            ->get('/calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar')
                ->where('month', '2026-07')
                ->where('label', 'July 2026')
                ->where('previousMonth', '2026-06')
                ->where('nextMonth', '2026-08')
                ->where('leadingBlanks', 2)
                ->has('days', 31)
                ->where('days.0.date', '2026-07-01')
                ->where('days.14.isToday', true)
                ->etc()
            );
    }

    public function test_the_current_month_follows_the_users_timezone_not_the_servers(): void
    {
        // 23:30 UTC on 31 July is already 1 August in Auckland (UTC+12).
        $this->travelTo(CarbonImmutable::parse('2026-07-31 23:30:00', 'UTC'));

        $this->actingAs(User::factory()->inTimezone('Pacific/Auckland')->create())
            ->get('/calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-08')
                ->where('days.0.isToday', true)
                ->etc()
            );
    }

    public function test_it_marks_days_that_have_a_check_in(): void
    {
        $user = User::factory()->create();

        DailyCheckin::factory()
            ->for($user)
            ->on(CarbonImmutable::parse('2026-07-09'))
            ->createQuietly();

        $this->actingAs($user)
            ->get('/calendar/2026-07')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.8.date', '2026-07-09')
                ->where('days.8.hasCheckin', true)
                ->where('days.8.level', 1)
                ->where('days.7.hasCheckin', false)
                ->where('days.7.level', 0)
                ->etc()
            );
    }

    public function test_it_does_not_mark_another_users_check_ins(): void
    {
        $user = User::factory()->create();

        DailyCheckin::factory()
            ->for(User::factory()->create())
            ->on(CarbonImmutable::parse('2026-07-09'))
            ->createQuietly();

        $this->actingAs($user)
            ->get('/calendar/2026-07')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.8.hasCheckin', false)
                ->etc()
            );
    }

    public function test_it_reads_the_months_check_ins_in_a_single_query(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 10) as $dayOfMonth) {
            DailyCheckin::factory()
                ->for($user)
                ->on(CarbonImmutable::parse(sprintf('2026-07-%02d', $dayOfMonth)))
                ->createQuietly();
        }

        $checkinQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$checkinQueries): void {
            if (str_contains($query->sql, 'daily_checkins')) {
                $checkinQueries++;
            }
        });

        $this->actingAs($user)->get('/calendar/2026-07')->assertOk();

        $this->assertSame(1, $checkinQueries);
    }

    public function test_it_excludes_check_ins_outside_the_requested_month(): void
    {
        $user = User::factory()->create();

        DailyCheckin::factory()
            ->for($user)
            ->on(CarbonImmutable::parse('2026-06-30'))
            ->createQuietly();

        DailyCheckin::factory()
            ->for($user)
            ->on(CarbonImmutable::parse('2026-08-01'))
            ->createQuietly();

        $this->actingAs($user)
            ->get('/calendar/2026-07')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.hasCheckin', false)
                ->where('days.30.hasCheckin', false)
                ->etc()
            );
    }

    public function test_it_offers_the_neighbouring_months_across_a_year_boundary(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/calendar/2026-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('previousMonth', '2025-12')
                ->where('nextMonth', '2026-02')
                ->etc()
            );
    }

    public function test_it_rejects_a_semantically_invalid_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/calendar/2026-13')
            ->assertNotFound();
    }

    public function test_guests_cannot_reach_the_calendar(): void
    {
        $this->get('/calendar/2026-07')->assertRedirect(route('login'));
    }
}
