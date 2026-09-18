<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DayConditionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The journal is bounded to days the user has lived through, so the suite
     * pins its own today rather than leaning on the wall clock being past July
     * 2026.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC'));
    }

    public function test_a_single_tap_persists_a_rating(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 7])
            ->assertRedirect('/day/2026-07-15');

        $log = ConditionLog::query()->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($condition->id, $log->condition_id);
        $this->assertSame('2026-07-15', $log->date->toDateString());
        $this->assertSame(7, $log->intensity);
    }

    public function test_the_rating_reloads_with_the_day(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->hue(ConditionHue::Moss)->createQuietly(['name' => 'Headache']);

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 7]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conditions.0.name', 'Headache')
                ->where('conditions.0.hue', ConditionHue::Moss->value)
                ->where('conditions.0.intensity', 7)
                ->where('conditions.0.level', 4)
                ->where('level', 4)
                ->etc()
            );
    }

    public function test_a_second_tap_edits_the_same_row_rather_than_adding_one(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 3]);
        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 9]);

        $this->assertSame(1, ConditionLog::query()->count());
        $this->assertSame(9, ConditionLog::query()->sole()->intensity);
    }

    public function test_a_rating_of_zero_is_a_record_not_a_gap(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 0]);

        $this->assertSame(0, ConditionLog::query()->sole()->intensity);
    }

    public function test_it_rejects_a_rating_outside_the_scale(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 11])
            ->assertSessionHasErrors('intensity');

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_it_rejects_a_date_that_matches_the_pattern_but_does_not_exist(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-02-31/conditions/{$condition->id}", ['intensity' => 4])
            ->assertSessionHasErrors('date');

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_it_refuses_a_day_that_has_not_happened_yet(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/9999-12-31/conditions/{$condition->id}", ['intensity' => 4])
            ->assertSessionHasErrors(['date' => 'That day has not happened yet.']);

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_it_refuses_a_day_before_the_account_existed(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-14/conditions/{$condition->id}", ['intensity' => 4])
            ->assertSessionHasErrors(['date' => 'Your journal starts on 15 July 2026.']);

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_the_bound_follows_the_users_own_today_rather_than_the_servers(): void
    {
        // 23:30 UTC on the 15th is already the 16th in Auckland (UTC+12 in July).
        $this->travelTo(CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC'));

        $user = User::factory()->inTimezone('Pacific/Auckland')->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-16/conditions/{$condition->id}", ['intensity' => 4])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-07-16', ConditionLog::query()->sole()->date->toDateString());
    }

    public function test_the_clear_control_takes_a_rating_back_off_the_record(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 7]);

        $this->actingAs($user)
            ->delete("/day/2026-07-15/conditions/{$condition->id}")
            ->assertRedirect('/day/2026-07-15');

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_it_refuses_to_clear_another_users_rating(): void
    {
        $owner = User::factory()->create();
        $condition = Condition::factory()->for($owner)->createQuietly();

        $this->actingAs($owner)->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 7]);

        $this->actingAs(User::factory()->tracking()->create())
            ->delete("/day/2026-07-15/conditions/{$condition->id}")
            ->assertForbidden();

        $this->assertSame(1, ConditionLog::query()->count());
    }

    public function test_a_rating_stranded_outside_the_bound_can_still_be_cleared(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::query()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'date' => '2526-01-01',
            'intensity' => 4,
        ]);

        $this->actingAs($user)
            ->delete("/day/2526-01-01/conditions/{$condition->id}")
            ->assertRedirect('/day/2526-01-01');

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_clearing_a_day_that_carries_no_rating_is_a_404(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->delete("/day/2026-07-15/conditions/{$condition->id}")
            ->assertNotFound();
    }

    public function test_guests_cannot_clear_a_rating(): void
    {
        $condition = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->delete("/day/2026-07-15/conditions/{$condition->id}")
            ->assertRedirect(route('login'));
    }

    public function test_it_refuses_to_rate_another_users_condition(): void
    {
        $theirs = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->actingAs(User::factory()->tracking()->create())
            ->post("/day/2026-07-15/conditions/{$theirs->id}", ['intensity' => 4])
            ->assertForbidden();

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_it_refuses_to_rate_a_condition_that_has_been_stopped(): void
    {
        $user = User::factory()->tracking()->create();
        $stopped = Condition::factory()->for($user)->inactive()->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-15/conditions/{$stopped->id}", ['intensity' => 4])
            ->assertForbidden();

        $this->assertSame(0, ConditionLog::query()->count());
    }

    public function test_guests_cannot_record_a_rating(): void
    {
        $condition = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->post("/day/2026-07-15/conditions/{$condition->id}", ['intensity' => 4])
            ->assertRedirect(route('login'));

        $this->assertSame(0, ConditionLog::query()->count());
    }
}
