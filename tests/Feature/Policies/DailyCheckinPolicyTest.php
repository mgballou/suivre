<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\DailyCheckin;
use App\Models\User;
use App\Policies\DailyCheckinPolicy;

it('allows the owner to view, update and delete their check-in', function (): void {
    $owner = User::factory()->createQuietly();
    $checkin = DailyCheckin::factory()->for($owner)->createQuietly();
    $policy = new DailyCheckinPolicy();

    expect($policy->view($owner, $checkin)->allowed())->toBeTrue();
    expect($policy->update($owner, $checkin)->allowed())->toBeTrue();
    expect($policy->delete($owner, $checkin)->allowed())->toBeTrue();
});

it('denies another user from viewing, updating or deleting the check-in', function (): void {
    $owner = User::factory()->createQuietly();
    $other = User::factory()->createQuietly();
    $checkin = DailyCheckin::factory()->for($owner)->createQuietly();
    $policy = new DailyCheckinPolicy();

    expect($policy->view($other, $checkin)->denied())->toBeTrue();
    expect($policy->update($other, $checkin)->denied())->toBeTrue();
    expect($policy->delete($other, $checkin)->denied())->toBeTrue();
});

it('allows any user to create a check-in', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new DailyCheckinPolicy();

    expect($policy->create($user)->allowed())->toBeTrue();
});

it('allows any authenticated operator a backstage oversight list and bulk delete', function (): void {
    $user = User::factory()->createQuietly();
    $policy = new DailyCheckinPolicy();

    expect($policy->viewAny($user)->allowed())->toBeTrue();
    expect($policy->deleteAny($user)->allowed())->toBeTrue();
});
