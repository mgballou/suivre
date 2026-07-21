<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conditions;

use App\Filament\Resources\Conditions\Pages\ListConditions;
use App\Filament\Resources\Conditions\Pages\ViewCondition;
use App\Filament\Resources\Conditions\Schemas\ConditionInfolist;
use App\Filament\Resources\Conditions\Schemas\ConditionsTable;
use App\Models\Condition;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of the conditions a user tracks. Conditions are
 * user-generated data, authored and maintained in the user app; the backstage
 * has read-only visibility only, never create/edit/delete.
 *
 * @extends resource<Condition>
 */
class ConditionResource extends Resource
{
    protected static ?string $model = Condition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Conditions';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Condition>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
            ->withCount(['conditionLogs', 'flareEvents']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConditionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConditionsTable::configure($table);
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
            'index' => ListConditions::route('/'),
            'view' => ViewCondition::route('/{record}'),
        ];
    }
}
