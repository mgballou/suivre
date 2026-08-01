<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Auth\Access\Response;

beforeEach(function (): void {
    $this->policy = new UserPolicy();
    $this->admin = User::factory()->admin()->createQuietly();
    $this->member = User::factory()->createQuietly();
    $this->subject = User::factory()->createQuietly();
});

it('returns a Response rather than a bool from every ability', function (): void {
    expect($this->policy->viewAny($this->admin))->toBeInstanceOf(Response::class);
    expect($this->policy->view($this->admin, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->create($this->admin))->toBeInstanceOf(Response::class);
    expect($this->policy->update($this->admin, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->setRole($this->admin, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->resetPassword($this->admin, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->delete($this->admin, $this->subject))->toBeInstanceOf(Response::class);
    expect($this->policy->deleteAny($this->admin))->toBeInstanceOf(Response::class);
});

it('lets an administrator list and view any account', function (): void {
    expect($this->policy->viewAny($this->admin)->allowed())->toBeTrue();
    expect($this->policy->view($this->admin, $this->subject)->allowed())->toBeTrue();
});

it('restricts an ordinary user to viewing only their own account', function (): void {
    expect($this->policy->viewAny($this->member)->denied())->toBeTrue();
    expect($this->policy->view($this->member, $this->member)->allowed())->toBeTrue();
    expect($this->policy->view($this->member, $this->subject)->denied())->toBeTrue();
});

it('forbids editing or deleting an account from the backstage', function (): void {
    expect($this->policy->update($this->admin, $this->subject)->denied())->toBeTrue();
    expect($this->policy->delete($this->admin, $this->subject)->denied())->toBeTrue();
    expect($this->policy->deleteAny($this->admin)->denied())->toBeTrue();
});

it('lets an administrator create an account, since registration no longer can', function (): void {
    expect($this->policy->create($this->admin)->allowed())->toBeTrue();
    expect($this->policy->create($this->member)->denied())->toBeTrue();
});
