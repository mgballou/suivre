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

class DayCheckinControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_tap_persists_a_check_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/day/2026-07-15/checkin', ['mood' => MoodLevel::Good->value])
            ->assertRedirect('/day/2026-07-15');

        $checkin = DailyCheckin::query()->sole();

        $this->assertSame($user->id, $checkin->user_id);
        $this->assertSame('2026-07-15', $checkin->date->toDateString());
        $this->assertSame(MoodLevel::Good, $checkin->mood);
        $this->assertNull($checkin->sleep);
        $this->assertNull($checkin->stress);
    }

    public function test_revisiting_the_day_shows_the_saved_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/day/2026-07-15/checkin', [
            'mood' => MoodLevel::Good->value,
            'sleep' => SleepQuality::Poor->value,
            'stress' => StressLevel::Moderate->value,
            'note' => 'long walk',
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('level', 1)
                ->where('checkin.mood', MoodLevel::Good->value)
                ->where('checkin.sleep', SleepQuality::Poor->value)
                ->where('checkin.stress', StressLevel::Moderate->value)
                ->where('checkin.note', 'long walk')
                ->etc()
            );
    }

    public function test_a_second_tap_edits_the_same_row_rather_than_adding_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/day/2026-07-15/checkin', ['mood' => MoodLevel::Low->value]);
        $this->actingAs($user)->post('/day/2026-07-15/checkin', [
            'mood' => MoodLevel::Good->value,
            'stress' => StressLevel::Low->value,
        ]);

        $this->assertSame(1, DailyCheckin::query()->count());

        $checkin = DailyCheckin::query()->sole();

        $this->assertSame(MoodLevel::Good, $checkin->mood);
        $this->assertSame(StressLevel::Low, $checkin->stress);
    }

    public function test_each_user_gets_their_own_row_for_the_same_day(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        DailyCheckin::factory()
            ->for($theirs)
            ->on(CarbonImmutable::parse('2026-07-15'))
            ->createQuietly(['mood' => MoodLevel::Low]);

        $this->actingAs($mine)->post('/day/2026-07-15/checkin', ['mood' => MoodLevel::Good->value]);

        $this->assertSame(2, DailyCheckin::query()->count());
        $this->assertSame(
            MoodLevel::Low,
            DailyCheckin::query()->where('user_id', $theirs->id)->sole()->mood,
        );
    }

    public function test_a_blank_note_is_stored_as_null(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/day/2026-07-15/checkin', [
            'mood' => MoodLevel::Good->value,
            'note' => '   ',
        ]);

        $this->assertNull(DailyCheckin::query()->sole()->note);
    }

    public function test_it_rejects_a_value_outside_the_scale(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/day/2026-07-15/checkin', ['mood' => 99])
            ->assertSessionHasErrors('mood');

        $this->assertSame(0, DailyCheckin::query()->count());
    }

    public function test_it_rejects_a_date_that_matches_the_pattern_but_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/day/2026-02-31/checkin', ['mood' => MoodLevel::Good->value])
            ->assertSessionHasErrors('date');

        $this->assertSame(0, DailyCheckin::query()->count());
    }

    public function test_guests_cannot_record_a_check_in(): void
    {
        $this->post('/day/2026-07-15/checkin', ['mood' => MoodLevel::Good->value])
            ->assertRedirect(route('login'));

        $this->assertSame(0, DailyCheckin::query()->count());
    }
}
