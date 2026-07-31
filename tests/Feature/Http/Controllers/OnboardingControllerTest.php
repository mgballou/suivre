<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_conditions_is_sent_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/calendar')
            ->assertRedirect(route('onboarding.conditions'));
    }

    public function test_the_gate_covers_every_journal_surface(): void
    {
        $user = User::factory()->create();

        foreach (['/calendar', '/calendar/2026-07', '/day/2026-07-15', '/insights'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertRedirect(route('onboarding.conditions'));
        }
    }

    public function test_the_gate_leaves_settings_reachable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/profile')
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get('/settings/conditions')
            ->assertOk();
    }

    public function test_onboarding_offers_suggestions_to_start_from(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/onboarding/conditions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('onboarding/conditions')
                ->has('suggestions', 7)
                ->where('suggestions.0.name', 'Joint pain')
                ->where('suggestions.0.hue', ConditionHue::Clay->value)
            );
    }

    public function test_a_user_who_already_tracks_something_is_sent_on(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/onboarding/conditions')
            ->assertRedirect(route('calendar'));
    }

    public function test_choosing_conditions_starts_tracking_them_and_opens_the_calendar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/conditions', [
                'conditions' => [
                    ['name' => 'Joint pain', 'color' => ConditionHue::Clay->value],
                    ['name' => 'Brain fog', 'color' => ConditionHue::Indigo->value],
                ],
            ])
            ->assertRedirect(route('calendar'));

        $conditions = Condition::query()->orderBy('name')->get();

        $this->assertCount(2, $conditions);
        $this->assertSame(['Brain fog', 'Joint pain'], $conditions->pluck('name')->all());
        $this->assertTrue($conditions->every(fn (Condition $condition): bool => $condition->user_id === $user->id));
        $this->assertTrue($conditions->every(fn (Condition $condition): bool => $condition->is_active));
    }

    public function test_the_calendar_opens_once_onboarding_is_done(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/onboarding/conditions', [
            'conditions' => [['name' => 'Joint pain', 'color' => ConditionHue::Clay->value]],
        ]);

        $this->actingAs($user)->get('/calendar')->assertOk();
    }

    public function test_it_rejects_a_colour_outside_the_curated_set(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/onboarding/conditions', [
                'conditions' => [['name' => 'Joint pain', 'color' => '#ff00ff']],
            ])
            ->assertSessionHasErrors('conditions.0.color');

        $this->assertSame(0, Condition::query()->count());
    }

    public function test_it_rejects_the_same_name_twice_in_one_submission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/onboarding/conditions', [
                'conditions' => [
                    ['name' => 'Joint pain', 'color' => ConditionHue::Clay->value],
                    ['name' => 'joint pain', 'color' => ConditionHue::Moss->value],
                ],
            ])
            ->assertSessionHasErrors('conditions.0.name');

        $this->assertSame(0, Condition::query()->count());
    }

    public function test_it_rejects_an_empty_selection(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/onboarding/conditions', ['conditions' => []])
            ->assertSessionHasErrors('conditions');
    }

    public function test_guests_cannot_reach_onboarding(): void
    {
        $this->get('/onboarding/conditions')->assertRedirect(route('login'));
    }
}
