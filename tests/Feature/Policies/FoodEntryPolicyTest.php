<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\FoodEntry;
use App\Models\Meal;
use App\Models\User;
use App\Policies\FoodEntryPolicy;

beforeEach(function (): void {
    $this->policy = new FoodEntryPolicy();
    $this->owner = User::factory()->createQuietly();
    $this->other = User::factory()->createQuietly();
    $this->meal = Meal::factory()->for($this->owner)->createQuietly();
    $this->foodEntry = FoodEntry::factory()->forMeal($this->meal)->createQuietly();
});

it('allows the owner of the parent meal to view, update and delete', function (): void {
    expect($this->policy->view($this->owner, $this->foodEntry)->allowed())->toBeTrue();
    expect($this->policy->update($this->owner, $this->foodEntry)->allowed())->toBeTrue();
    expect($this->policy->delete($this->owner, $this->foodEntry)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating and deleting', function (): void {
    expect($this->policy->view($this->other, $this->foodEntry)->denied())->toBeTrue();
    expect($this->policy->update($this->other, $this->foodEntry)->denied())->toBeTrue();
    expect($this->policy->delete($this->other, $this->foodEntry)->denied())->toBeTrue();
});

it('resolves ownership without the meal relation preloaded', function (): void {
    $entry = FoodEntry::factory()->forMeal($this->meal)->createQuietly()->fresh();

    expect($entry->relationLoaded('meal'))->toBeFalse();
    expect($this->policy->view($this->owner, $entry)->allowed())->toBeTrue();
});

it('allows any user to create', function (): void {
    expect($this->policy->create($this->other)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new FoodEntryPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
});
