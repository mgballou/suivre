<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Condition;
use App\Models\User;

it('persists a condition belonging to a user', function (): void {
    $user = User::factory()->createQuietly();

    $condition = Condition::factory()->for($user)->createQuietly();

    expect($condition->exists)->toBeTrue();
    $this->assertDatabaseHas('conditions', [
        'id' => $condition->id,
        'user_id' => $user->id,
    ]);
});

it('casts is_active to a boolean', function (): void {
    $condition = Condition::factory()->inactive()->createQuietly();

    expect($condition->fresh()->is_active)->toBeFalse();
});

it('resolves the user it belongs to', function (): void {
    $user = User::factory()->createQuietly();

    $condition = Condition::factory()->for($user)->createQuietly();

    expect($condition->fresh()->user)->toBeInstanceOf(User::class);
    expect($condition->fresh()->user->id)->toBe($user->id);
});

it('resolves its morph alias from the enforced morph map', function (): void {
    expect((new Condition())->getMorphClass())->toBe('conditions');
});
