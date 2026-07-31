<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Condition;
use App\Models\User;
use App\Policies\ConditionPolicy;

beforeEach(function (): void {
    $this->policy = new ConditionPolicy();
    $this->owner = User::factory()->createQuietly();
    $this->other = User::factory()->createQuietly();
    $this->condition = Condition::factory()->for($this->owner)->createQuietly();
});

it('allows the owner to view, update and delete', function (): void {
    expect($this->policy->view($this->owner, $this->condition)->allowed())->toBeTrue();
    expect($this->policy->update($this->owner, $this->condition)->allowed())->toBeTrue();
    expect($this->policy->delete($this->owner, $this->condition)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating and deleting', function (): void {
    expect($this->policy->view($this->other, $this->condition)->denied())->toBeTrue();
    expect($this->policy->update($this->other, $this->condition)->denied())->toBeTrue();
    expect($this->policy->delete($this->other, $this->condition)->denied())->toBeTrue();
});

it('allows any user to create', function (): void {
    expect($this->policy->create($this->other)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new ConditionPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
});

it('allows the owner to record against a condition they still track', function (): void {
    expect($this->policy->record($this->owner, $this->condition)->allowed())->toBeTrue();
});

it('denies recording against a condition the owner has stopped', function (): void {
    $stopped = Condition::factory()->for($this->owner)->inactive()->createQuietly();

    expect($this->policy->record($this->owner, $stopped)->denied())->toBeTrue();
});

it('denies recording against another users condition', function (): void {
    expect($this->policy->record($this->other, $this->condition)->denied())->toBeTrue();
});
