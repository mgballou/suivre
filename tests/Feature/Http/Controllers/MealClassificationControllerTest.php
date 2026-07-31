<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Category;
use App\Models\FoodItem;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealClassificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suggests_a_catalog_match_with_its_tags(): void
    {
        $user = User::factory()->tracking()->create();
        $dairy = Category::factory()->createQuietly(['name' => 'Dairy']);
        $item = FoodItem::factory()->createQuietly(['name' => 'cheddar cheese']);
        $item->categories()->attach($dairy);

        $this->actingAs($user)
            ->postJson('/meals/classify', ['lines' => ['cheddar cheese']])
            ->assertOk()
            ->assertJsonPath('lines.0.text', 'cheddar cheese')
            ->assertJsonPath('lines.0.foodItemId', $item->id)
            ->assertJsonPath('lines.0.foodItemName', 'cheddar cheese')
            ->assertJsonPath('lines.0.tags.0', 'Dairy')
            ->assertJsonPath('lines.0.matched', true);
    }

    public function test_it_reports_a_miss_rather_than_guessing(): void
    {
        $user = User::factory()->tracking()->create();
        FoodItem::factory()->createQuietly(['name' => 'cheddar cheese']);

        $this->actingAs($user)
            ->postJson('/meals/classify', ['lines' => ['zzzzqqqq wibble']])
            ->assertOk()
            ->assertJsonPath('lines.0.matched', false)
            ->assertJsonPath('lines.0.foodItemId', null)
            ->assertJsonPath('lines.0.tags', []);
    }

    public function test_it_answers_one_line_per_line_asked(): void
    {
        $user = User::factory()->tracking()->create();
        FoodItem::factory()->createQuietly(['name' => 'porridge']);

        $this->actingAs($user)
            ->postJson('/meals/classify', ['lines' => ['porridge', 'zzzzqqqq', 'porridge']])
            ->assertOk()
            ->assertJsonCount(3, 'lines');
    }

    public function test_classifying_saves_nothing(): void
    {
        // The preview answers a question about text; the meal is only created
        // once the user confirms.
        $user = User::factory()->tracking()->create();
        FoodItem::factory()->createQuietly(['name' => 'porridge']);

        $this->actingAs($user)->postJson('/meals/classify', ['lines' => ['porridge']])->assertOk();

        $this->assertSame(0, Meal::query()->count());
    }

    public function test_blank_lines_are_dropped_before_classifying(): void
    {
        $user = User::factory()->tracking()->create();
        FoodItem::factory()->createQuietly(['name' => 'porridge']);

        $this->actingAs($user)
            ->postJson('/meals/classify', ['lines' => ['porridge', '   ']])
            ->assertOk()
            ->assertJsonCount(1, 'lines');
    }

    public function test_it_rejects_an_empty_request(): void
    {
        $this->actingAs(User::factory()->tracking()->create())
            ->postJson('/meals/classify', ['lines' => []])
            ->assertUnprocessable();
    }

    public function test_a_guest_cannot_classify(): void
    {
        $this->postJson('/meals/classify', ['lines' => ['porridge']])->assertUnauthorized();
    }
}
