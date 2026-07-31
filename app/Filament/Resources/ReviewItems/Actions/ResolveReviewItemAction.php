<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Actions;

use App\Models\FoodItem;
use App\Models\ReviewItem;
use App\Services\Food\Actions\ResolveReviewItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/**
 * "The catalog already knows this food, under another name."
 *
 * Picking the match records the queued text as an alias, so the classifier
 * resolves it on its own next time — the operator answers each question once.
 */
class ResolveReviewItemAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Link to a catalog food')
            ->icon(Heroicon::OutlinedLink)
            ->color('success')
            ->modalHeading('Link to a catalog food')
            ->modalDescription('The queued text is saved as a synonym of the food you pick, so it matches by itself from now on.')
            ->modalSubmitActionLabel('Link and resolve')
            ->visible(fn (ReviewItem $record): bool => $record->isOpen())
            ->authorize(fn (ReviewItem $record): bool => auth()->user()?->can('decide', $record) ?? false)
            ->schema([
                Select::make('food_item_id')
                    ->label('Catalog food')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => FoodItem::query()
                        ->where('normalized_name', 'like', '%' . FoodItem::normalizeName($search) . '%')
                        ->orderBy('normalized_name')
                        ->limit(25)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(static fn (string $value): ?string => FoodItem::query()->find($value)?->name),
            ])
            ->action(function (ReviewItem $record, array $data): void {
                /** @var FoodItem $foodItem */
                $foodItem = FoodItem::query()->findOrFail($data['food_item_id']);

                app(ResolveReviewItem::class)($record, $foodItem);
            })
            ->successNotificationTitle('Linked. That text will match on its own from now on.');
    }

    public static function getDefaultName(): ?string
    {
        return 'resolve';
    }
}
