<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOrderedCases;

/**
 * The two surfaces every ramp is defined against. D20 forbids deriving one from
 * the other — a light ramp climbs by getting darker and a dark ramp by getting
 * lighter, so each scheme names its own values rather than inheriting a flip.
 */
enum ColorScheme: string
{
    use HasOrderedCases;

    case Light = 'light';
    case Dark = 'dark';

    public function isLight(): bool
    {
        return $this === self::Light;
    }

    public function isDark(): bool
    {
        return $this === self::Dark;
    }

    /**
     * The page surface a ramp sits on.
     */
    public function background(): string
    {
        return match ($this) {
            self::Light => '#fbfbfa',
            self::Dark => '#131314',
        };
    }
}
