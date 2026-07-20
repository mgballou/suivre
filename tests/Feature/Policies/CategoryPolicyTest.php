<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\CategoryPolicy;
use Illuminate\Auth\Access\Response;

it('returns a Response rather than a bool from every ability', function (): void {
    $user = User::factory()->createQuietly();
    $category = Category::factory()->createQuietly();
    $policy = app(CategoryPolicy::class);

    expect($policy->viewAny($user))->toBeInstanceOf(Response::class);
    expect($policy->view($user, $category))->toBeInstanceOf(Response::class);
    expect($policy->create($user))->toBeInstanceOf(Response::class);
    expect($policy->update($user, $category))->toBeInstanceOf(Response::class);
    expect($policy->delete($user, $category))->toBeInstanceOf(Response::class);
});

it('allows any authenticated operator to curate the global taxonomy', function (): void {
    $user = User::factory()->createQuietly();
    $category = Category::factory()->createQuietly();

    expect($user->can('viewAny', Category::class))->toBeTrue();
    expect($user->can('view', $category))->toBeTrue();
    expect($user->can('create', Category::class))->toBeTrue();
    expect($user->can('update', $category))->toBeTrue();
    expect($user->can('delete', $category))->toBeTrue();
});

it('is not scoped to the user who created it', function (): void {
    $category = Category::factory()->createQuietly();
    $otherUser = User::factory()->createQuietly();

    expect($otherUser->can('update', $category))->toBeTrue();
});
