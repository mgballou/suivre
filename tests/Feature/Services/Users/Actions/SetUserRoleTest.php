<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\Actions\SetUserRole;
use Filament\Facades\Filament;

it('gives a self-registered account backstage access', function (): void {
    $member = User::factory()->createQuietly();

    app(SetUserRole::class)($member, UserRole::Admin);

    expect($member->fresh()?->isAdmin())->toBeTrue();
    expect($member->fresh()?->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('takes backstage access away again', function (): void {
    $admin = User::factory()->admin()->createQuietly();

    app(SetUserRole::class)($admin, UserRole::Member);

    expect($admin->fresh()?->isAdmin())->toBeFalse();
});

it('holds an account to exactly one role', function (): void {
    // Assigning rather than syncing would leave the account holding both, and
    // isAdmin() would keep answering true for someone just made a member.
    $admin = User::factory()->admin()->createQuietly();

    app(SetUserRole::class)($admin, UserRole::Member);

    expect($admin->fresh()?->roles()->pluck('name')->all())->toBe([UserRole::Member->value]);
});

it('returns the account reading the role it was just given', function (): void {
    $member = User::factory()->createQuietly();

    $updated = app(SetUserRole::class)($member, UserRole::Admin);

    expect($updated->role)->toBe(UserRole::Admin);
});

it('leaves the journal the account logged alone', function (): void {
    $member = User::factory()->inTimezone('Pacific/Auckland')->createQuietly();

    app(SetUserRole::class)($member, UserRole::Admin);

    expect($member->fresh()?->timezone)->toBe('Pacific/Auckland');
    expect($member->fresh()?->email)->toBe($member->email);
});
