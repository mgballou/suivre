<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use UnitEnum;

/**
 * Exposes an enum's cases in their declared (display) order for UI rendering.
 *
 * @phpstan-require-implements UnitEnum
 */
trait HasOrderedCases
{
    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
