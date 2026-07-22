<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs;

use App\Filament\Resources\ConditionLogs\Pages\ListConditionLogs;
use App\Filament\Resources\ConditionLogs\Pages\ViewConditionLog;
use App\Filament\Resources\ConditionLogs\Schemas\ConditionLogInfolist;
use App\Filament\Resources\ConditionLogs\Schemas\ConditionLogsTable;
use App\Models\ConditionLog;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of the daily 0–10 condition ratings. Ratings are
 * user-generated, authored in the user app; the backstage has read-only
 * visibility only, never create/edit/delete.
 *
 * @extends resource<ConditionLog>
 */
class ConditionLogResource extends Resource
{
    protected static ?string $model = ConditionLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Conditions';

    protected static ?string $modelLabel = 'condition rating';

    protected static ?string $pluralModelLabel = 'condition ratings';

    /**
     * @return Builder<ConditionLog>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'condition']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConditionLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConditionLogsTable::configure($table);
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
            'index' => ListConditionLogs::route('/'),
            'view' => ViewConditionLog::route('/{record}'),
        ];
    }
}
