<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries;

use App\Filament\Resources\FoodEntries\Pages\EditFoodEntry;
use App\Filament\Resources\FoodEntries\Pages\ListFoodEntries;
use App\Filament\Resources\FoodEntries\Pages\ViewFoodEntry;
use App\Filament\Resources\FoodEntries\Schemas\FoodEntriesTable;
use App\Filament\Resources\FoodEntries\Schemas\FoodEntryForm;
use App\Filament\Resources\FoodEntries\Schemas\FoodEntryInfolist;
use App\Models\FoodEntry;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of the food line items inside a meal — the precursor to
 * the classification review queue: the "Pending classification" filter is how
 * an operator finds the free text the classifier has not resolved yet.
 *
 * `meal.user` is eager-loaded because `FoodEntry::$eaten_at` reads its parent
 * meal and the owner column reads through to the user; strict mode forbids
 * lazy-loading either, so the base query loads the whole chain once.
 *
 * @extends resource<FoodEntry>
 */
class FoodEntryResource extends Resource
{
    protected static ?string $model = FoodEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Journal';

    protected static ?string $modelLabel = 'food entry';

    protected static ?string $pluralModelLabel = 'food entries';

    /**
     * @return Builder<FoodEntry>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('meal.user');
    }

    public static function form(Schema $schema): Schema
    {
        return FoodEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FoodEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoodEntriesTable::configure($table);
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
            'index' => ListFoodEntries::route('/'),
            'view' => ViewFoodEntry::route('/{record}'),
            'edit' => EditFoodEntry::route('/{record}/edit'),
        ];
    }
}
