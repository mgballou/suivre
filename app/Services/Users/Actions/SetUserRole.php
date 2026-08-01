<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Puts an account on exactly one platform role.
 *
 * `syncRoles` rather than `assignRole` is the whole point: roles are mutually
 * exclusive here, so granting one must revoke the other in the same breath.
 * Assigning would leave an account holding both, and `isAdmin()` would keep
 * answering true for someone who had just been made a member.
 *
 * The relation is reloaded afterwards so the caller reads the role it just set
 * rather than the one cached on the instance.
 */
class SetUserRole
{
    public function __invoke(User $user, UserRole $role): User
    {
        $user->syncRoles([Role::findOrCreate($role->value)]);

        $user->load('roles');

        return $user;
    }
}
