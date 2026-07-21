<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\ConditionLog;
use App\Models\User;
use App\Policies\ConditionLogPolicy;

beforeEach(function (): void {
    $this->policy = new ConditionLogPolicy();
    $this->owner = User::factory()->createQuietly();
    $this->other = User::factory()->createQuietly();
    $this->log = ConditionLog::factory()->for($this->owner)->createQuietly();
});

it('allows the owner to view, update and delete', function (): void {
    expect($this->policy->view($this->owner, $this->log)->allowed())->toBeTrue();
    expect($this->policy->update($this->owner, $this->log)->allowed())->toBeTrue();
    expect($this->policy->delete($this->owner, $this->log)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating and deleting', function (): void {
    expect($this->policy->view($this->other, $this->log)->denied())->toBeTrue();
    expect($this->policy->update($this->other, $this->log)->denied())->toBeTrue();
    expect($this->policy->delete($this->other, $this->log)->denied())->toBeTrue();
});

it('allows any user to create', function (): void {
    expect($this->policy->create($this->other)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list and bulk delete', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new ConditionLogPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
    expect($policy->deleteAny($user)->allowed())->toBeTrue();
});
