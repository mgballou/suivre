<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StagingSeeder extends Seeder
{
    /**
     * Idempotent staging accounts: an admin (Filament backstage oversight) and a
     * throwaway user for real-world app use with no backstage access. Passwords
     * are staging-only.
     *
     * `email_verified_at` is set as a property, not mass-assigned — it is
     * absent from the model's Fillable and strict mode would throw otherwise.
     */
    public function run(): void
    {
        $this->upsertUser('admin@suivre.staging', 'Staging Admin', admin: true);
        $this->upsertUser('user@suivre.staging', 'Throwaway User', admin: false);
    }

    private function upsertUser(string $email, string $name, bool $admin): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make('<redacted>'),
        ]);
        $user->email_verified_at = now();
        $user->save();

        // syncRoles keeps the seeder idempotent: re-running never stacks or
        // strands roles, and the throwaway user is explicitly held to none.
        $user->syncRoles($admin ? [UserRole::Admin->value] : []);
    }
}
