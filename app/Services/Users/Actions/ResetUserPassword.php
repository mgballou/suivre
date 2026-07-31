<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sets an account's password on its owner's behalf — the recovery path for an
 * installation with no mail transport wired up.
 *
 * The remember token is rolled at the same time, and that is the part worth
 * being deliberate about: a "remember me" cookie issued before the reset would
 * otherwise keep working, so whoever held the old session would still be signed
 * in after the password they knew stopped being valid. Rolling it makes the
 * reset mean what an operator assumes it means.
 */
class ResetUserPassword
{
    public function __invoke(User $user, string $password): User
    {
        $user->password = Hash::make($password);
        $user->setRememberToken(Str::random(60));
        $user->save();

        return $user;
    }
}
