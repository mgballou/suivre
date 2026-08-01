<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\Actions\ResetUserPassword;
use Database\Seeders\StagingSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(StagingSeeder::class);
});

function stagingUser(string $email): User
{
    return User::query()->where('email', $email)->sole();
}

it('seeds an administrator and a throwaway member', function (): void {
    expect(stagingUser('admin@suivre.staging')->isAdmin())->toBeTrue();
    expect(stagingUser('user@suivre.staging')->isAdmin())->toBeFalse();
});

it('lets the seeded accounts sign in without a mail transport', function (): void {
    $admin = stagingUser('admin@suivre.staging');

    expect(Hash::check(StagingSeeder::INITIAL_PASSWORD, $admin->password))->toBeTrue();
    expect($admin->email_verified_at)->not->toBeNull();
});

it('does not undo a password changed since the account was seeded', function (): void {
    // This seeder runs on every boot of the web container. Re-filling the
    // password would revert it to a value published in the repo, silently, on
    // the next restart.
    app(ResetUserPassword::class)(stagingUser('admin@suivre.staging'), 'something-only-i-know');

    $this->seed(StagingSeeder::class);

    expect(Hash::check('something-only-i-know', stagingUser('admin@suivre.staging')->password))->toBeTrue();
});

it('adds no accounts on a second run', function (): void {
    $users = User::query()->count();

    $this->seed(StagingSeeder::class);

    expect(User::query()->count())->toBe($users);
});

it('never stacks a second role on a re-run', function (): void {
    $this->seed(StagingSeeder::class);

    expect(stagingUser('admin@suivre.staging')->roles()->pluck('name')->all())
        ->toBe([UserRole::Admin->value]);
});
