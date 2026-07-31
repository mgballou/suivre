<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FlareEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConditionActivationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stopping_a_condition_keeps_every_record_it_has(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::factory()
            ->for($user)
            ->for($condition)
            ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 6]);

        FlareEvent::factory()->for($user)->for($condition)->createQuietly();

        $this->actingAs($user)
            ->put("/settings/conditions/{$condition->id}/activation", ['is_active' => false])
            ->assertRedirect('/settings/conditions');

        $this->assertFalse($condition->refresh()->is_active);
        $this->assertSame(1, Condition::query()->count());
        $this->assertSame(1, ConditionLog::query()->count());
        $this->assertSame(1, FlareEvent::query()->count());
    }

    public function test_a_stopped_condition_leaves_the_day_view(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly(['name' => 'Headache']);

        ConditionLog::factory()
            ->for($user)
            ->for($condition)
            ->createQuietly(['date' => CarbonImmutable::parse('2026-07-15'), 'intensity' => 6]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertInertia(fn (Assert $page) => $page->has('conditions', 1)->etc());

        $this->actingAs($user)->put("/settings/conditions/{$condition->id}/activation", ['is_active' => false]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertInertia(fn (Assert $page) => $page->has('conditions', 0)->etc());
    }

    public function test_resuming_a_condition_brings_it_back(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->inactive()->createQuietly();

        $this->actingAs($user)
            ->put("/settings/conditions/{$condition->id}/activation", ['is_active' => true])
            ->assertRedirect('/settings/conditions');

        $this->assertTrue($condition->refresh()->is_active);
    }

    public function test_it_refuses_to_stop_another_users_condition(): void
    {
        $condition = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->actingAs(User::factory()->create())
            ->put("/settings/conditions/{$condition->id}/activation", ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($condition->refresh()->is_active);
    }

    public function test_stopping_every_condition_does_not_send_the_user_back_to_onboarding(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->put("/settings/conditions/{$condition->id}/activation", ['is_active' => false]);

        $this->actingAs($user)->get('/calendar')->assertOk();
    }
}
