<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\FlareIntensity;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DayFlareControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_taps_log_a_flare(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
                'intensity' => FlareIntensity::Moderate->value,
            ])
            ->assertRedirect('/day/2026-07-15');

        $flare = FlareEvent::query()->sole();

        $this->assertSame($user->id, $flare->user_id);
        $this->assertSame($condition->id, $flare->condition_id);
        $this->assertSame(FlareIntensity::Moderate, $flare->intensity);
        $this->assertNull($flare->duration_minutes);
        $this->assertNull($flare->note);
    }

    public function test_it_records_the_detail_when_it_is_offered(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Severe->value,
            'duration_minutes' => 90,
            'note' => 'started after lunch',
        ]);

        $flare = FlareEvent::query()->sole();

        $this->assertSame(90, $flare->duration_minutes);
        $this->assertSame('started after lunch', $flare->note);
    }

    public function test_a_flare_logged_today_is_stamped_with_the_time_it_happened(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-15 14:32:00', 'UTC'));

        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Mild->value,
        ]);

        $this->assertSame('14:32', FlareEvent::query()->sole()->occurred_at->format('H:i'));
    }

    public function test_a_back_filled_flare_lands_at_midday_on_the_day_it_names(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC'));

        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Mild->value,
        ]);

        $this->assertSame('2026-07-15 12:00', FlareEvent::query()->sole()->occurred_at->format('Y-m-d H:i'));
    }

    public function test_the_flare_reloads_with_the_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-15 14:32:00', 'UTC'));

        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly(['name' => 'Headache']);

        $this->actingAs($user)->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Moderate->value,
            'duration_minutes' => 75,
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('flares', 1)
                ->where('flares.0.conditionName', 'Headache')
                ->where('flares.0.intensity', 'Moderate')
                ->where('flares.0.time', '14:32')
                ->where('flares.0.duration', '1 h 15 min')
                ->etc()
            );
    }

    public function test_a_flare_belongs_to_the_day_it_was_logged_against_in_the_users_timezone(): void
    {
        // 23:30 UTC on the 15th is already the 16th in Auckland (UTC+12 in July).
        $this->travelTo(CarbonImmutable::parse('2026-07-15 23:30:00', 'UTC'));

        $user = User::factory()->inTimezone('Pacific/Auckland')->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)->post("/day/2026-07-16/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Mild->value,
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-16')
            ->assertInertia(fn (Assert $page) => $page->has('flares', 1)->etc());

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertInertia(fn (Assert $page) => $page->has('flares', 0)->etc());
    }

    public function test_it_offers_the_flare_intensities_from_the_domain_enum(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->get('/day/2026-07-15')
            ->assertInertia(fn (Assert $page) => $page
                ->has('flareIntensities', 3)
                ->where('flareIntensities.0.label', 'Mild')
                ->where('flareIntensities.2.label', 'Severe')
                ->etc()
            );
    }

    public function test_it_rejects_an_intensity_outside_the_scale(): void
    {
        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        $this->actingAs($user)
            ->post("/day/2026-07-15/conditions/{$condition->id}/flares", ['intensity' => 9])
            ->assertSessionHasErrors('intensity');

        $this->assertSame(0, FlareEvent::query()->count());
    }

    public function test_it_refuses_to_log_against_another_users_condition(): void
    {
        $theirs = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->actingAs(User::factory()->tracking()->create())
            ->post("/day/2026-07-15/conditions/{$theirs->id}/flares", [
                'intensity' => FlareIntensity::Mild->value,
            ])
            ->assertForbidden();

        $this->assertSame(0, FlareEvent::query()->count());
    }

    public function test_it_does_not_show_another_users_flares(): void
    {
        $theirs = User::factory()->create();

        FlareEvent::factory()
            ->for($theirs)
            ->for(Condition::factory()->for($theirs))
            ->createQuietly(['occurred_at' => CarbonImmutable::parse('2026-07-15 10:00:00')]);

        $this->actingAs(User::factory()->tracking()->create())
            ->get('/day/2026-07-15')
            ->assertInertia(fn (Assert $page) => $page->has('flares', 0)->etc());
    }

    public function test_guests_cannot_log_a_flare(): void
    {
        $condition = Condition::factory()->for(User::factory()->create())->createQuietly();

        $this->post("/day/2026-07-15/conditions/{$condition->id}/flares", [
            'intensity' => FlareIntensity::Mild->value,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, FlareEvent::query()->count());
    }
}
