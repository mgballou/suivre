<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Meal;
use App\Models\User;
use App\Policies\MealPolicy;

beforeEach(function (): void {
    $this->policy = new MealPolicy();
    $this->owner = User::factory()->createQuietly();
    $this->other = User::factory()->createQuietly();
    $this->meal = Meal::factory()->for($this->owner)->createQuietly();
});

it('allows the owner to view, update and delete', function (): void {
    expect($this->policy->view($this->owner, $this->meal)->allowed())->toBeTrue();
    expect($this->policy->update($this->owner, $this->meal)->allowed())->toBeTrue();
    expect($this->policy->delete($this->owner, $this->meal)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating and deleting', function (): void {
    expect($this->policy->view($this->other, $this->meal)->denied())->toBeTrue();
    expect($this->policy->update($this->other, $this->meal)->denied())->toBeTrue();
    expect($this->policy->delete($this->other, $this->meal)->denied())->toBeTrue();
});

it('allows any user to create', function (): void {
    expect($this->policy->create($this->other)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new MealPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
});
