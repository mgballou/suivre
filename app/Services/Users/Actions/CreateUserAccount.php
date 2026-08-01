<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Creates an account on someone's behalf. With public registration closed this
 * is the only way an account comes into being, so it carries the two things
 * registration used to settle for itself.
 *
 * The timezone is required rather than defaulted. The whole journal is keyed on
 * the user's local day (D5), so a wrong one files meals and ratings against the
 * wrong date — and the account's owner, who never saw the setting, has no reason
 * to go looking for it. Making the operator choose turns a silent default into a
 * visible decision; the profile screen remains the correction.
 *
 * The email is marked verified because an operator typing it in has confirmed it
 * out of band, and this install has no mail transport to confirm it any other way.
 */
class CreateUserAccount
{
    public function __invoke(
        string $name,
        string $email,
        string $password,
        string $timezone,
        UserRole $role,
    ): User {
        $user = new User();

        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'timezone' => $timezone,
        ]);
        $user->email_verified_at = now();
        $user->save();

        return app(SetUserRole::class)($user, $role);
    }
}
