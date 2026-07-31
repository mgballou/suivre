<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Category;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\ReviewItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DayMealControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_meal_against_the_day(): void
    {
        $user = User::factory()->tracking()->create();
        $item = FoodItem::factory()->createQuietly(['name' => 'porridge']);

        $this->actingAs($user)
            ->post('/day/2026-07-20/meals', [
                'meal_type' => 'breakfast',
                'entries' => [['text' => 'porridge', 'food_item_id' => $item->id]],
            ])
            ->assertRedirect('/day/2026-07-20');

        $meal = Meal::query()->sole();

        $this->assertSame($user->id, $meal->user_id);
        $this->assertTrue($meal->meal_type->isBreakfast());
        $this->assertSame($item->id, $meal->entries()->sole()->food_item_id);
    }

    public function test_an_unmatched_line_saves_and_reaches_the_review_queue(): void
    {
        $user = User::factory()->tracking()->create();

        $this->actingAs($user)
            ->post('/day/2026-07-20/meals', [
                'meal_type' => 'dinner',
                'entries' => [['text' => 'grandmas stew', 'food_item_id' => null]],
            ])
            ->assertRedirect('/day/2026-07-20');

        $this->assertSame(1, Meal::query()->count());
        $this->assertSame('grandmas stew', ReviewItem::query()->sole()->text);
    }

    public function test_the_saved_meal_appears_on_the_day_with_its_tags(): void
    {
        $user = User::factory()->tracking()->create();
        $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
        $item = FoodItem::factory()->createQuietly(['name' => 'whole milk']);
        $item->categories()->attach($dairy);

        $this->actingAs($user)->post('/day/2026-07-20/meals', [
            'meal_type' => 'breakfast',
            'entries' => [['text' => 'milk', 'food_item_id' => $item->id]],
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('day')
                ->has('meals', 1)
                ->where('meals.0.type', 'Breakfast')
                ->where('meals.0.entries.0.label', 'whole milk')
                ->where('meals.0.entries.0.tags.0', 'Dairy')
                ->where('meals.0.entries.0.matched', true)
            );
    }

    public function test_an_unmatched_line_is_shown_as_the_user_typed_it(): void
    {
        $user = User::factory()->tracking()->create();

        $this->actingAs($user)->post('/day/2026-07-20/meals', [
            'meal_type' => 'snack',
            'entries' => [['text' => 'aunt bettys slice', 'food_item_id' => null]],
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meals.0.entries.0.label', 'aunt bettys slice')
                ->where('meals.0.entries.0.matched', false)
                ->where('meals.0.entries.0.tags', [])
            );
    }

    public function test_a_meal_is_rejected_without_a_meal_type(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->post('/day/2026-07-20/meals', [
                'entries' => [['text' => 'toast', 'food_item_id' => null]],
            ])
            ->assertSessionHasErrors('meal_type');
    }

    public function test_a_meal_is_rejected_without_any_entries(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->post('/day/2026-07-20/meals', ['meal_type' => 'lunch', 'entries' => []])
            ->assertSessionHasErrors('entries');
    }

    public function test_an_invented_catalog_reference_is_rejected(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->post('/day/2026-07-20/meals', [
                'meal_type' => 'lunch',
                'entries' => [['text' => 'toast', 'food_item_id' => 99999]],
            ])
            ->assertSessionHasErrors('entries.0.food_item_id');
    }

    public function test_a_guest_cannot_log_a_meal(): void
    {
        $this->post('/day/2026-07-20/meals', [
            'meal_type' => 'lunch',
            'entries' => [['text' => 'toast', 'food_item_id' => null]],
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Meal::query()->count());
    }

    public function test_a_users_day_never_shows_another_users_meals(): void
    {
        $user = User::factory()->tracking()->create();
        $other = User::factory()->tracking()->create();

        $this->actingAs($other)->post('/day/2026-07-20/meals', [
            'meal_type' => 'lunch',
            'entries' => [['text' => 'their lunch', 'food_item_id' => null]],
        ]);

        $this->actingAs($user)
            ->get('/day/2026-07-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('meals', 0));
    }

    public function test_a_meal_logged_today_is_stored_at_the_users_real_local_time(): void
    {
        // The timezone regression: a wall-clock reading written as UTC moves
        // the instant by the user's offset and can change its day.
        $this->travelTo(CarbonImmutable::parse('2026-07-25 09:00:00', 'UTC'));

        $user = User::factory()->tracking()->inTimezone('Pacific/Auckland')->create();

        $this->actingAs($user)->post('/day/2026-07-25/meals', [
            'meal_type' => 'dinner',
            'entries' => [['text' => 'soup', 'food_item_id' => null]],
        ]);

        $meal = Meal::query()->sole();

        $this->assertSame(
            '2026-07-25 21:00',
            $meal->eaten_at->setTimezone($user->timezone)->format('Y-m-d H:i'),
        );
    }
}
