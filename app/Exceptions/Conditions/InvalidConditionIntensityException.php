<?php

declare(strict_types=1);

namespace App\Exceptions\Conditions;

use DomainException;

/**
 * Thrown when a daily condition rating falls outside the supported 0–10 scale.
 */
class InvalidConditionIntensityException extends DomainException
{
    public static function make(int $intensity): self
    {
        return new self("Condition intensity [{$intensity}] must be between 0 and 10.");
    }
}
