<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Actions;

use App\Models\ReviewItem;
use App\Services\Food\Actions\DismissReviewItem;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * "Not worth a catalog entry."
 *
 * A typo, a one-off, something too vague to tag. The entry stays unclassified
 * and contributes nothing to correlation, which is the honest outcome — the
 * engine reads categories, and this text has none to give it.
 */
class DismissReviewItemAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Dismiss')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Dismiss this entry')
            ->modalDescription('The catalog is left alone and the entry stays unclassified.')
            ->visible(fn (ReviewItem $record): bool => $record->isOpen())
            ->authorize(fn (ReviewItem $record): bool => auth()->user()?->can('decide', $record) ?? false)
            ->action(fn (ReviewItem $record) => app(DismissReviewItem::class)($record))
            ->successNotificationTitle('Dismissed.');
    }

    public static function getDefaultName(): ?string
    {
        return 'dismiss';
    }
}
