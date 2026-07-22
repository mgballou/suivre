<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A user's platform role. The value is the `roles.name` stored by
 * spatie/laravel-permission, so the role name has a single source of truth. Every
 * account holds exactly one: `Admin` grants read-only oversight of every user's
 * journal from the Filament backstage; `Member` is an ordinary app user with no
 * backstage access.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Member = 'member';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Member => 'Member',
        };
    }
}
