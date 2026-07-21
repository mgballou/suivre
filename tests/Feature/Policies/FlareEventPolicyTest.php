<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\FlareEvent;
use App\Models\User;
use App\Policies\FlareEventPolicy;

beforeEach(function (): void {
    $this->policy = new FlareEventPolicy();
    $this->owner = User::factory()->createQuietly();
    $this->other = User::factory()->createQuietly();
    $this->flareEvent = FlareEvent::factory()->for($this->owner)->createQuietly();
});

it('allows the owner to view, update and delete', function (): void {
    expect($this->policy->view($this->owner, $this->flareEvent)->allowed())->toBeTrue();
    expect($this->policy->update($this->owner, $this->flareEvent)->allowed())->toBeTrue();
    expect($this->policy->delete($this->owner, $this->flareEvent)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating and deleting', function (): void {
    expect($this->policy->view($this->other, $this->flareEvent)->denied())->toBeTrue();
    expect($this->policy->update($this->other, $this->flareEvent)->denied())->toBeTrue();
    expect($this->policy->delete($this->other, $this->flareEvent)->denied())->toBeTrue();
});

it('allows any user to create', function (): void {
    expect($this->policy->create($this->other)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list and bulk delete', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new FlareEventPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
    expect($policy->deleteAny($user)->allowed())->toBeTrue();
});
