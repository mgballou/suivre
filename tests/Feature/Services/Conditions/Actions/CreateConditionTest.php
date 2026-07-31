<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conditions\Actions;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;
use App\Services\Conditions\Actions\CreateCondition;

it('starts tracking a condition for the given user', function (): void {
    $user = User::factory()->createQuietly();

    $condition = app(CreateCondition::class)(
        user: $user,
        name: 'Brain fog',
        color: ConditionHue::Indigo,
    );

    expect($condition->user_id)->toBe($user->id);
    expect($condition->name)->toBe('Brain fog');
    expect($condition->color)->toBe(ConditionHue::Indigo);
    expect($condition->is_active)->toBeTrue();
});

it('gives a condition an icon the backstage can render', function (): void {
    $condition = app(CreateCondition::class)(
        user: User::factory()->createQuietly(),
        name: 'Brain fog',
        color: ConditionHue::Indigo,
    );

    expect($condition->icon)->not->toBeEmpty();
});

it('never touches another users conditions', function (): void {
    $theirs = User::factory()->createQuietly();
    Condition::factory()->for($theirs)->createQuietly(['name' => 'Theirs']);

    app(CreateCondition::class)(
        user: User::factory()->createQuietly(),
        name: 'Mine',
        color: ConditionHue::Moss,
    );

    expect($theirs->conditions()->pluck('name')->all())->toBe(['Theirs']);
});
