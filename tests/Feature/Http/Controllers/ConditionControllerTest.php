<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\FlareEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_users_conditions_active_ones_first(): void
    {
        $user = User::factory()->create();

        Condition::factory()->for($user)->inactive()->createQuietly(['name' => 'Nausea']);
        Condition::factory()->for($user)->createQuietly(['name' => 'Brain fog']);

        $this->actingAs($user)
            ->get('/settings/conditions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/conditions')
                ->has('conditions', 2)
                ->where('conditions.0.name', 'Brain fog')
                ->where('conditions.0.isActive', true)
                ->where('conditions.1.name', 'Nausea')
                ->where('conditions.1.isActive', false)
                ->etc()
            );
    }

    public function test_it_reports_what_each_condition_has_recorded(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::factory()->count(3)->for($user)->for($condition)->createQuietly();
        FlareEvent::factory()->for($user)->for($condition)->createQuietly();

        $this->actingAs($user)
            ->get('/settings/conditions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('conditions.0.ratings', 3)
                ->where('conditions.0.flares', 1)
                ->etc()
            );
    }

    public function test_it_offers_the_curated_hues_and_nothing_else(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/conditions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('hues', count(ConditionHue::cases()))
                ->where('hues.0.value', ConditionHue::Clay->value)
                ->where('hues.0.group', 'warm')
                ->where('hues.3.group', 'cool')
                ->etc()
            );
    }

    public function test_it_does_not_list_another_users_conditions(): void
    {
        Condition::factory()->for(User::factory()->create())->createQuietly(['name' => 'Not mine']);

        $this->actingAs(User::factory()->create())
            ->get('/settings/conditions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('conditions', 0)->etc());
    }

    public function test_it_starts_tracking_a_new_condition(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/conditions', [
                'name' => 'Brain fog',
                'color' => ConditionHue::Indigo->value,
            ])
            ->assertRedirect('/settings/conditions');

        $condition = Condition::query()->sole();

        $this->assertSame($user->id, $condition->user_id);
        $this->assertSame('Brain fog', $condition->name);
        $this->assertSame(ConditionHue::Indigo, $condition->color);
        $this->assertTrue($condition->is_active);
    }

    public function test_it_rejects_a_colour_outside_the_curated_set(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/settings/conditions', ['name' => 'Brain fog', 'color' => '#ff00ff'])
            ->assertSessionHasErrors('color');

        $this->assertSame(0, Condition::query()->count());
    }

    public function test_it_rejects_a_name_the_user_already_tracks(): void
    {
        $user = User::factory()->create();

        Condition::factory()->for($user)->createQuietly(['name' => 'Brain fog']);

        $this->actingAs($user)
            ->post('/settings/conditions', ['name' => 'Brain fog', 'color' => ConditionHue::Moss->value])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Condition::query()->count());
    }

    public function test_two_users_may_track_the_same_name(): void
    {
        Condition::factory()->for(User::factory()->create())->createQuietly(['name' => 'Brain fog']);

        $this->actingAs(User::factory()->create())
            ->post('/settings/conditions', ['name' => 'Brain fog', 'color' => ConditionHue::Moss->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Condition::query()->count());
    }

    public function test_it_renames_and_recolours_a_condition(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->hue(ConditionHue::Clay)->createQuietly(['name' => 'Headache']);

        $this->actingAs($user)
            ->patch("/settings/conditions/{$condition->id}", [
                'name' => 'Migraine',
                'color' => ConditionHue::Plum->value,
            ])
            ->assertRedirect('/settings/conditions');

        $condition->refresh();

        $this->assertSame('Migraine', $condition->name);
        $this->assertSame(ConditionHue::Plum, $condition->color);
    }

    public function test_a_condition_may_keep_its_own_name_when_edited(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->hue(ConditionHue::Clay)->createQuietly(['name' => 'Headache']);

        $this->actingAs($user)
            ->patch("/settings/conditions/{$condition->id}", [
                'name' => 'Headache',
                'color' => ConditionHue::Moss->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ConditionHue::Moss, $condition->refresh()->color);
    }

    public function test_it_refuses_to_edit_another_users_condition(): void
    {
        $condition = Condition::factory()
            ->for(User::factory()->create())
            ->createQuietly(['name' => 'Not mine']);

        $this->actingAs(User::factory()->create())
            ->patch("/settings/conditions/{$condition->id}", [
                'name' => 'Mine now',
                'color' => ConditionHue::Moss->value,
            ])
            ->assertForbidden();

        $this->assertSame('Not mine', $condition->refresh()->name);
    }

    public function test_there_is_no_way_to_delete_a_condition(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->delete("/settings/conditions/{$condition->id}")
            ->assertMethodNotAllowed();

        $this->assertSame(1, Condition::query()->count());
    }

    public function test_guests_cannot_manage_conditions(): void
    {
        $this->get('/settings/conditions')->assertRedirect(route('login'));
        $this->post('/settings/conditions', ['name' => 'Brain fog', 'color' => ConditionHue::Moss->value])
            ->assertRedirect(route('login'));
    }
}
