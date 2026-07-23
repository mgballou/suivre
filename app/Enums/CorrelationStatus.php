<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a correlation report carries a ranking at all.
 *
 * SUI-36 found that below roughly 90 days of logging the honest precision of a
 * ranked "suspects" list is a coin flip (30-day precision@1 peaks at 0.58 even
 * for a strong trigger). A thin ranking is therefore not a low-confidence
 * ranking — it is a different outcome, and this enum is what makes the two
 * impossible to confuse at a read site.
 */
enum CorrelationStatus: string
{
    case InsufficientData = 'insufficient_data';
    case Ready = 'ready';

    /**
     * Whether the report carries a ranking the UI may render.
     */
    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    /**
     * Whether the user has not yet logged enough to be told anything.
     */
    public function isInsufficient(): bool
    {
        return $this === self::InsufficientData;
    }
}
