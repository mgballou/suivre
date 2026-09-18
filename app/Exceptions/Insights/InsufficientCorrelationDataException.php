<?php

declare(strict_types=1);

namespace App\Exceptions\Insights;

use DomainException;

/**
 * Thrown when a ranking is read off a report that never produced one because
 * the user has not logged enough days yet.
 *
 * The exception is the guard rail behind `CorrelationStatus`: a caller that
 * forgets to check the status gets a loud failure rather than an empty list it
 * would happily render as "no triggers found".
 */
class InsufficientCorrelationDataException extends DomainException
{
    public static function make(int $comparableDays, int $requiredDays): self
    {
        return new self(
            "Correlation suspects need [{$requiredDays}] comparable days; the user has [{$comparableDays}]."
        );
    }
}
