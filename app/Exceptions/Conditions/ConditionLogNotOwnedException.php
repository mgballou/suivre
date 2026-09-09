<?php

declare(strict_types=1);

namespace App\Exceptions\Conditions;

use App\Models\ConditionLog;
use App\Models\User;
use DomainException;

/**
 * Thrown when an Action is asked to operate on a daily rating that belongs to a
 * different user than the acting one.
 */
class ConditionLogNotOwnedException extends DomainException
{
    public static function make(User $user, ConditionLog $conditionLog): self
    {
        return new self("Condition log [{$conditionLog->id}] is not owned by user [{$user->id}].");
    }
}
