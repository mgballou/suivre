<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\IntensityTrendWidget;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class IntensityTrendWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_renders_for_an_authenticated_operator(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(IntensityTrendWidget::class)
            ->assertOk()
            ->assertSee('Condition intensity');
    }

    public function test_it_plots_the_worst_daily_rating_over_the_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-30 09:00:00', 'UTC'));

        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse('2026-07-30'))
            ->createQuietly(['intensity' => 7]);

        $this->actingAs($user);

        $widget = new IntensityTrendWidget();
        $getData = new ReflectionMethod($widget, 'getData');
        /** @var array<string, mixed> $data */
        $data = $getData->invoke($widget);

        $this->assertSame('Condition intensity', $data['datasets'][0]['label']);
        $this->assertCount(30, $data['datasets'][0]['data']);
        $this->assertSame(7, $data['datasets'][0]['data'][29]);
        $this->assertNull($data['datasets'][0]['data'][0]);
        $this->assertSame('#4e8483', $data['datasets'][0]['borderColor']);
    }
}
