<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems;

use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewItems\Pages\ListReviewItems;
use App\Filament\Resources\ReviewItems\Pages\ViewReviewItem;
use App\Filament\Resources\ReviewItems\Schemas\ReviewItemInfolist;
use App\Filament\Resources\ReviewItems\Schemas\ReviewItemsTable;
use App\Models\ReviewItem;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The classification review queue — D9's human in the loop.
 *
 * Every food the deterministic classifier could not place lands here rather than
 * interrupting the person logging their lunch. Working the queue is curation:
 * each decision either grows the catalog or closes the question, and the queue
 * shrinks as the catalog learns.
 *
 * @extends resource<ReviewItem>
 */
class ReviewItemResource extends Resource
{
    protected static ?string $model = ReviewItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Taxonomy';

    protected static ?string $modelLabel = 'review item';

    protected static ?string $pluralModelLabel = 'review queue';

    /**
     * The count of what is still waiting, on the navigation item itself — the
     * queue is only useful if an operator can see it has filled up without
     * opening it.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = ReviewItem::query()
            ->whereIn('status', ReviewStatus::open())
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReviewItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewItemsTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListReviewItems::route('/'),
            'view' => ViewReviewItem::route('/{record}'),
        ];
    }
}
