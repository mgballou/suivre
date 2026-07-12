<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Events\Conditions\ConditionRated;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Exceptions\Conditions\InvalidConditionIntensityException;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use App\Services\Conditions\Actions\RateCondition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

it('records a rating and dispatches the event after commit', function (): void {
    $condition = Condition::factory()->createQuietly();
    $user = $condition->user;

    $log = app(RateCondition::class)($user, $condition, CarbonImmutable::parse('2026-07-06'), 5);

    expect($log)->toBeInstanceOf(ConditionLog::class);
    expect($log->intensity)->toBe(5);
    $this->assertDatabaseHas('condition_logs', [
        'user_id' => $user->id,
        'condition_id' => $condition->id,
        'intensity' => 5,
    ]);
    Event::assertDispatched(ConditionRated::class, function (ConditionRated $event) use ($log): bool {
        expect($event->conditionLog->id)->toBe($log->id);

        return true;
    });
});

it('upserts rather than duplicating the rating for the same day', function (): void {
    $condition = Condition::factory()->createQuietly();
    $user = $condition->user;
    $date = CarbonImmutable::parse('2026-07-06');
    $action = app(RateCondition::class);

    $action($user, $condition, $date, 3);
    $second = $action($user, $condition, $date, 8);

    expect(ConditionLog::count())->toBe(1);
    expect($second->fresh()->intensity)->toBe(8);
});

it('rejects rating a condition owned by another user', function (): void {
    $condition = Condition::factory()->createQuietly();
    $intruder = User::factory()->createQuietly();

    expect(fn () => app(RateCondition::class)($intruder, $condition, CarbonImmutable::parse('2026-07-06'), 5))
        ->toThrow(ConditionNotOwnedException::class);
});

it('rejects an intensity outside the 0 to 10 scale', function (int $intensity): void {
    $condition = Condition::factory()->createQuietly();

    expect(fn () => app(RateCondition::class)($condition->user, $condition, CarbonImmutable::parse('2026-07-06'), $intensity))
        ->toThrow(InvalidConditionIntensityException::class);
})->with([-1, 11]);
