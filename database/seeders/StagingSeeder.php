<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StagingSeeder extends Seeder
{
    /**
     * The initial password for a seeded account, and only ever the initial one —
     * see `upsertUser`.
     */
    public const string INITIAL_PASSWORD = '<redacted>';

    /**
     * Idempotent staging accounts: an admin (Filament backstage oversight) and a
     * throwaway member for real-world app use with no backstage access. Passwords
     * are staging-only.
     */
    public function run(): void
    {
        $this->upsertUser('admin@suivre.staging', 'Staging Admin', UserRole::Admin);
        $this->upsertUser('user@suivre.staging', 'Throwaway User', UserRole::Member);
    }

    private function upsertUser(string $email, string $name, UserRole $role): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        // The password is set on creation and never again. This seeder runs on
        // every boot of the web container, so filling it unconditionally would
        // silently undo a password changed from the backstage the next time the
        // service restarted — and the value it reverted to is published in the
        // repo. `email_verified_at` is set as a property, not mass-assigned: it
        // is absent from the model's Fillable and strict mode would throw.
        if (! $user->exists) {
            $user->password = Hash::make(self::INITIAL_PASSWORD);
            $user->email_verified_at = now();
        }

        $user->name = $name;
        $user->save();

        // syncRoles keeps the seeder idempotent: re-running never stacks roles and
        // holds each account to exactly its one role.
        $user->syncRoles([Role::findOrCreate($role->value)]);
    }
}
