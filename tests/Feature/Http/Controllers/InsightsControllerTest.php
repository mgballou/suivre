<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_trend_and_heatmap_for_the_user(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 09:00:00', 'UTC'));

        $user = User::factory()->tracking()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse('2026-07-30'))
            ->createQuietly(['intensity' => 7]);

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('insights')
                ->has('trend.points', 30)
                ->where('trend.loggedDays', 1)
                ->where('trend.points.29.values.intensity', 7)
                ->where('month.month', '2026-07')
                ->has('month.days', 31)
                ->where('month.days.29.level', 4)
                ->etc()
            );
    }

    public function test_it_leaves_unlogged_days_null_and_at_step_zero(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 09:00:00', 'UTC'));

        $this->actingAs(User::factory()->tracking()->create())
            ->get('/insights')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('trend.loggedDays', 0)
                ->where('trend.points.0.values.intensity', null)
                ->where('month.days.0.level', 0)
                ->etc()
            );
    }

    public function test_guests_cannot_reach_insights(): void
    {
        $this->get('/insights')->assertRedirect(route('login'));
    }
}
