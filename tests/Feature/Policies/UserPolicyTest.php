<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Auth\Access\Response;

beforeEach(function (): void {
    $this->policy = new UserPolicy();
    $this->operator = User::factory()->createQuietly();
    $this->subject = User::factory()->createQuietly();
});

it('returns a Response rather than a bool from every ability', function (): void {
    expect($this->policy->viewAny($this->operator))->toBeInstanceOf(Response::class);
    expect($this->policy->view($this->operator, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->create($this->operator))->toBeInstanceOf(Response::class);
    expect($this->policy->update($this->operator, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->delete($this->operator, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->deleteAny($this->operator))->toBeInstanceOf(Response::class);
});

it('allows listing and viewing an account', function (): void {
    expect($this->policy->viewAny($this->operator)->allowed())->toBeTrue();
    expect($this->policy->view($this->operator, $this->subject)->allowed())->toBeTrue();
});

it('forbids creating, editing or deleting an account from the backstage', function (): void {
    expect($this->policy->create($this->operator)->denied())->toBeTrue();
    expect($this->policy->update($this->operator, $this->subject)->denied())->toBeTrue();
    expect($this->policy->delete($this->operator, $this->subject)->denied())->toBeTrue();
    expect($this->policy->deleteAny($this->operator)->denied())->toBeTrue();
});
