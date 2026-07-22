<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Platform;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use App\Services\Platform\PlatformMetricsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformMetricsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_entries_across_all_users_grouped_by_day(): void
    {
        $start = CarbonImmutable::parse('2026-07-01');
        $end = CarbonImmutable::parse('2026-07-31');

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conditionA = Condition::factory()->for($userA)->createQuietly();
        $conditionB = Condition::factory()->for($userB)->createQuietly();

        ConditionLog::factory()->forCondition($conditionA)->on(CarbonImmutable::parse('2026-07-10'))->createQuietly();
        ConditionLog::factory()->forCondition($conditionB)->on(CarbonImmutable::parse('2026-07-10'))->createQuietly();
        ConditionLog::factory()->forCondition($conditionA)->on(CarbonImmutable::parse('2026-07-11'))->createQuietly();

        $counts = app(PlatformMetricsRepository::class)->entriesLoggedPerDay($start, $end);

        $this->assertSame(2, $counts['2026-07-10']);
        $this->assertSame(1, $counts['2026-07-11']);
        $this->assertArrayNotHasKey('2026-07-12', $counts);
    }

    public function test_it_excludes_entries_outside_the_window(): void
    {
        $start = CarbonImmutable::parse('2026-07-01');
        $end = CarbonImmutable::parse('2026-07-31');

        $user = User::factory()->create();
        $condition = Condition::factory()->for($user)->createQuietly();

        ConditionLog::factory()->forCondition($condition)->on(CarbonImmutable::parse('2026-06-30'))->createQuietly();
        ConditionLog::factory()->forCondition($condition)->on(CarbonImmutable::parse('2026-08-01'))->createQuietly();

        $counts = app(PlatformMetricsRepository::class)->entriesLoggedPerDay($start, $end);

        $this->assertSame([], $counts);
    }
}
