<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Actions;

use App\Models\Category;
use App\Models\ReviewItem;
use App\Services\Food\Actions\CatalogReviewItem;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * "The catalog has never heard of this food."
 *
 * The only route by which a research category — histamine, nightshade, FODMAP —
 * ever reaches a food, since no dataset carries them (D10/D26). The name is
 * pre-filled with the queued text and meant to be corrected: the catalog wants
 * "sourdough bread", not whatever the user typed at speed.
 */
class CatalogReviewItemAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Add to the catalog')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('primary')
            ->modalHeading('Add to the catalog')
            ->modalDescription('Creates a curated catalog entry and resolves this item against it.')
            ->modalSubmitActionLabel('Add and resolve')
            ->visible(fn (ReviewItem $record): bool => $record->isOpen())
            ->authorize(fn (ReviewItem $record): bool => auth()->user()?->can('decide', $record) ?? false)
            ->fillForm(fn (ReviewItem $record): array => ['name' => $record->text])
            ->schema([
                TextInput::make('name')
                    ->label('Catalog name')
                    ->helperText('Tidy this up if you like — the queued text will still match this food.')
                    ->required()
                    ->maxLength(255),
                CheckboxList::make('categories')
                    ->label('Trigger categories')
                    ->helperText('What this food carries. Leave empty if none apply.')
                    ->options(fn (): array => Category::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->columns(2),
            ])
            ->action(function (ReviewItem $record, array $data): void {
                app(CatalogReviewItem::class)(
                    $record,
                    (string) $data['name'],
                    array_map(intval(...), $data['categories'] ?? []),
                );
            })
            ->successNotificationTitle('Added to the catalog and resolved.');
    }

    public static function getDefaultName(): ?string
    {
        return 'catalog';
    }
}
