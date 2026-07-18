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
