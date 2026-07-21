<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\EntriesLoggedWidget;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class EntriesLoggedWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_renders_for_an_authenticated_operator(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EntriesLoggedWidget::class)
            ->assertOk()
            ->assertSee('Entries logged');
    }

    public function test_it_counts_entries_across_all_users_per_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 09:00:00', 'UTC'));

        $today = CarbonImmutable::parse('2026-07-30');

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conditionA = Condition::factory()->for($userA)->createQuietly();
        $conditionB = Condition::factory()->for($userB)->createQuietly();

        ConditionLog::factory()->forCondition($conditionA)->on($today)->createQuietly();
        ConditionLog::factory()->forCondition($conditionB)->on($today)->createQuietly();

        $this->actingAs($userA);

        $widget = new EntriesLoggedWidget();
        $getData = new ReflectionMethod($widget, 'getData');
        /** @var array<string, mixed> $data */
        $data = $getData->invoke($widget);

        $this->assertSame('Entries logged', $data['datasets'][0]['label']);
        $this->assertCount(30, $data['datasets'][0]['data']);
        $this->assertSame(2, $data['datasets'][0]['data'][29]);
        $this->assertSame(0, $data['datasets'][0]['data'][0]);
        $this->assertSame('#4e8483', $data['datasets'][0]['backgroundColor']);
    }
}
