<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a queued classification miss stands (D9).
 *
 * An operator either resolves the miss — by curating the catalog so the text
 * would now match — or dismisses it as not worth catalog space. Both are
 * terminal; a resolved item is not re-queued, because the entry it describes
 * has already been through the classifier once.
 *
 * SUI-17 builds the Filament queue that moves items between these states. This
 * enum and the model exist here so the miss path has somewhere to land rather
 * than being silently dropped.
 */
enum ReviewStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-inbox',
            self::Resolved => 'heroicon-o-check-circle',
            self::Dismissed => 'heroicon-o-x-circle',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting an operator decision.',
            self::Resolved => 'The catalog was curated so this text now matches.',
            self::Dismissed => 'Judged not worth a catalog entry.',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }

    public function isDismissed(): bool
    {
        return $this === self::Dismissed;
    }

    /**
     * Whether the item still needs an operator. Paired with `open()` so the
     * queue's filter is read from the enum rather than rebuilt at a call site.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), strict: true);
    }

    /**
     * Statuses that still await a decision — what the review queue lists.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [
            self::Pending,
        ];
    }

    /**
     * Statuses an operator has already acted on.
     *
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [
            self::Resolved,
            self::Dismissed,
        ];
    }
}
