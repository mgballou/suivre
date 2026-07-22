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
     * Idempotent staging accounts: an admin (Filament backstage oversight) and a
     * throwaway member for real-world app use with no backstage access. Passwords
     * are staging-only.
     *
     * `email_verified_at` is set as a property, not mass-assigned — it is
     * absent from the model's Fillable and strict mode would throw otherwise.
     */
    public function run(): void
    {
        $this->upsertUser('admin@suivre.staging', 'Staging Admin', UserRole::Admin);
        $this->upsertUser('user@suivre.staging', 'Throwaway User', UserRole::Member);
    }

    private function upsertUser(string $email, string $name, UserRole $role): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make('<redacted>'),
        ]);
        $user->email_verified_at = now();
        $user->save();

        // syncRoles keeps the seeder idempotent: re-running never stacks roles and
        // holds each account to exactly its one role.
        $user->syncRoles([Role::findOrCreate($role->value)]);
    }
}
