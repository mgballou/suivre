<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StagingSeeder extends Seeder
{
    /**
     * Idempotent staging accounts: an admin (Filament backstage) and a
     * throwaway user for real-world app use. Passwords are staging-only.
     *
     * `email_verified_at` is set as a property, not mass-assigned — it is
     * absent from the model's Fillable and strict mode would throw otherwise.
     */
    public function run(): void
    {
        $this->upsertUser('admin@suivre.staging', 'Staging Admin');
        $this->upsertUser('user@suivre.staging', 'Throwaway User');
    }

    private function upsertUser(string $email, string $name): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make('suivre-staging'),
        ]);
        $user->email_verified_at = now();
        $user->save();
    }
}
