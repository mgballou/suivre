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

it('lets everyone read the taxonomy but only administrators curate it', function (): void {
    $admin = User::factory()->admin()->createQuietly();
    $member = User::factory()->createQuietly();
    $category = Category::factory()->createQuietly();

    expect($member->can('viewAny', Category::class))->toBeTrue();
    expect($member->can('view', $category))->toBeTrue();
    expect($member->can('create', Category::class))->toBeFalse();
    expect($member->can('update', $category))->toBeFalse();
    expect($member->can('delete', $category))->toBeFalse();

    expect($admin->can('create', Category::class))->toBeTrue();
    expect($admin->can('update', $category))->toBeTrue();
    expect($admin->can('delete', $category))->toBeTrue();
});

it('lets an administrator curate a category regardless of who created it', function (): void {
    $category = Category::factory()->createQuietly();
    $admin = User::factory()->admin()->createQuietly();

    expect($admin->can('update', $category))->toBeTrue();
});
