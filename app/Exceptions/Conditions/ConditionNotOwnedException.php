<?php

declare(strict_types=1);

namespace App\Exceptions\Conditions;

use App\Models\Condition;
use App\Models\User;
use DomainException;

/**
 * Thrown when an Action is asked to operate on a condition that belongs to a
 * different user than the acting one.
 */
class ConditionNotOwnedException extends DomainException
{
    public static function make(User $user, Condition $condition): self
    {
        return new self("Condition [{$condition->id}] is not owned by user [{$user->id}].");
    }
}
