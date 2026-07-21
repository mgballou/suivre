<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Journal\Actions;

use App\Enums\RampStep;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use App\Services\Journal\Actions\BuildIntensityMonth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('emits a step for every day of the month', function (): void {
    $user = User::factory()->createQuietly();

    $month = app(BuildIntensityMonth::class)($user, CarbonImmutable::parse('2026-07-15'));

    expect($month->days)->toHaveCount(31);
    expect($month->month)->toBe('2026-07');
    expect($month->label)->toBe('July 2026');
    expect($month->leadingBlanks)->toBe(2);
});

it('buckets a logged rating onto the shared ramp', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()
        ->forCondition($condition)
        ->on(CarbonImmutable::parse('2026-07-09'))
        ->createQuietly(['intensity' => 7]);

    $month = app(BuildIntensityMonth::class)($user, CarbonImmutable::parse('2026-07-01'));

    expect($month->days[8]->date)->toBe('2026-07-09');
    expect($month->days[8]->level)->toBe(RampStep::Strong);
    expect($month->days[7]->level)->toBe(RampStep::None);
});

it('takes the worst rating when a day carries several conditions', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-09');
    $calm = Condition::factory()->for($user)->createQuietly();
    $bad = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()->forCondition($calm)->on($date)->createQuietly(['intensity' => 2]);
    ConditionLog::factory()->forCondition($bad)->on($date)->createQuietly(['intensity' => 9]);

    $month = app(BuildIntensityMonth::class)($user, $date);

    expect($month->days[8]->level)->toBe(RampStep::Severe);
});

it('ignores another user’s ratings', function (): void {
    $user = User::factory()->createQuietly();
    $stranger = Condition::factory()->for(User::factory()->createQuietly())->createQuietly();

    ConditionLog::factory()
        ->forCondition($stranger)
        ->on(CarbonImmutable::parse('2026-07-09'))
        ->createQuietly(['intensity' => 9]);

    $month = app(BuildIntensityMonth::class)($user, CarbonImmutable::parse('2026-07-09'));

    expect($month->days[8]->level)->toBe(RampStep::None);
});

it('reads the whole month in a single query', function (): void {
    $user = User::factory()->createQuietly();
    $condition = Condition::factory()->for($user)->createQuietly();

    foreach (range(1, 10) as $day) {
        ConditionLog::factory()
            ->forCondition($condition)
            ->on(CarbonImmutable::parse(sprintf('2026-07-%02d', $day)))
            ->createQuietly();
    }

    $logQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$logQueries): void {
        if (str_contains($query->sql, 'condition_logs')) {
            $logQueries++;
        }
    });

    app(BuildIntensityMonth::class)($user, CarbonImmutable::parse('2026-07-01'));

    expect($logQueries)->toBe(1);
});
