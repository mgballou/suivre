<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Exceptions\Conditions\ConditionLogNotOwnedException;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\User;
use App\Services\Conditions\Actions\ClearConditionRating;

it('removes the days rating', function (): void {
    $condition = Condition::factory()->createQuietly();
    $log = ConditionLog::factory()->for($condition)->for($condition->user)->createQuietly();

    app(ClearConditionRating::class)($condition->user, $log);

    $this->assertDatabaseMissing('condition_logs', ['id' => $log->id]);
});

it('refuses to remove a rating belonging to someone else', function (): void {
    $condition = Condition::factory()->createQuietly();
    $log = ConditionLog::factory()->for($condition)->for($condition->user)->createQuietly();

    expect(fn () => app(ClearConditionRating::class)(User::factory()->createQuietly(), $log))
        ->toThrow(ConditionLogNotOwnedException::class);

    $this->assertDatabaseHas('condition_logs', ['id' => $log->id]);
});

it('removes a rating stranded on a date the journal no longer accepts', function (): void {
    $condition = Condition::factory()->createQuietly();
    $log = ConditionLog::factory()
        ->for($condition)
        ->for($condition->user)
        ->createQuietly(['date' => '2526-01-01']);

    app(ClearConditionRating::class)($condition->user, $log);

    $this->assertDatabaseMissing('condition_logs', ['id' => $log->id]);
});
