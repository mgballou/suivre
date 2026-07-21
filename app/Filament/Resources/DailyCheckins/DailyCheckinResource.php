<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins;

use App\Filament\Resources\DailyCheckins\Pages\EditDailyCheckin;
use App\Filament\Resources\DailyCheckins\Pages\ListDailyCheckins;
use App\Filament\Resources\DailyCheckins\Pages\ViewDailyCheckin;
use App\Filament\Resources\DailyCheckins\Schemas\DailyCheckinForm;
use App\Filament\Resources\DailyCheckins\Schemas\DailyCheckinInfolist;
use App\Filament\Resources\DailyCheckins\Schemas\DailyCheckinsTable;
use App\Models\DailyCheckin;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of the daily sleep / mood / stress check-in. Check-ins
 * are authored in the user app and are unique per `(user_id, date)`, so the
 * backstage corrects and removes but never creates.
 *
 * @extends resource<DailyCheckin>
 */
class DailyCheckinResource extends Resource
{
    protected static ?string $model = DailyCheckin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Journal';

    protected static ?string $modelLabel = 'daily check-in';

    protected static ?string $pluralModelLabel = 'daily check-ins';

    /**
     * @return Builder<DailyCheckin>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function form(Schema $schema): Schema
    {
        return DailyCheckinForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DailyCheckinInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyCheckinsTable::configure($table);
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
            'index' => ListDailyCheckins::route('/'),
            'view' => ViewDailyCheckin::route('/{record}'),
            'edit' => EditDailyCheckin::route('/{record}/edit'),
        ];
    }
}
