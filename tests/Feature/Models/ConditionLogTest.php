<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

it('persists a rating for a condition', function (): void {
    $condition = Condition::factory()->createQuietly();

    $log = ConditionLog::factory()->forCondition($condition)->createQuietly();

    expect($log->exists)->toBeTrue();
    $this->assertDatabaseHas('condition_logs', [
        'id' => $log->id,
        'condition_id' => $condition->id,
        'user_id' => $condition->user_id,
    ]);
});

it('casts intensity to an integer and date to an immutable date', function (): void {
    $log = ConditionLog::factory()->on(CarbonImmutable::parse('2026-07-06'))->createQuietly([
        'intensity' => 7,
    ]);

    $fresh = $log->fresh();

    expect($fresh->intensity)->toBe(7);
    expect($fresh->date)->toBeInstanceOf(CarbonImmutable::class);
    expect($fresh->date->toDateString())->toBe('2026-07-06');
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new ConditionLog())->getMorphClass())->toBe('condition_logs');
});

it('allows the same user to rate two conditions on one date', function (): void {
    $user = User::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-06');
    $first = Condition::factory()->for($user)->createQuietly();
    $second = Condition::factory()->for($user)->createQuietly();

    ConditionLog::factory()->forCondition($first)->on($date)->createQuietly();
    ConditionLog::factory()->forCondition($second)->on($date)->createQuietly();

    expect(ConditionLog::count())->toBe(2);
});

it('allows two users to rate on the same date', function (): void {
    $date = CarbonImmutable::parse('2026-07-06');

    ConditionLog::factory()->forCondition(Condition::factory()->createQuietly())->on($date)->createQuietly();
    ConditionLog::factory()->forCondition(Condition::factory()->createQuietly())->on($date)->createQuietly();

    expect(ConditionLog::count())->toBe(2);
});

it('rejects a second rating for the same user, condition and date', function (): void {
    $condition = Condition::factory()->createQuietly();
    $date = CarbonImmutable::parse('2026-07-06');

    ConditionLog::factory()->forCondition($condition)->on($date)->createQuietly();

    expect(fn () => ConditionLog::factory()->forCondition($condition)->on($date)->createQuietly())
        ->toThrow(UniqueConstraintViolationException::class);
});
