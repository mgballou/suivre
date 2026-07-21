<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals;

use App\Filament\Resources\Meals\Pages\EditMeal;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Meals\Pages\ViewMeal;
use App\Filament\Resources\Meals\Schemas\MealForm;
use App\Filament\Resources\Meals\Schemas\MealInfolist;
use App\Filament\Resources\Meals\Schemas\MealsTable;
use App\Models\Meal;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of eating occasions. Meals are authored in the user app;
 * the backstage corrects a mistyped instant or slot and removes bad rows.
 *
 * `user` is eager-loaded because `Meal::$date` resolves the user's local day
 * through their timezone and yields null unless the relation is loaded —
 * rendering it per row would otherwise be an N+1 under strict mode.
 *
 * @extends resource<Meal>
 */
class MealResource extends Resource
{
    protected static ?string $model = Meal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Journal';

    /**
     * @return Builder<Meal>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
            ->withCount('entries');
    }

    public static function form(Schema $schema): Schema
    {
        return MealForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MealInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealsTable::configure($table);
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
            'index' => ListMeals::route('/'),
            'view' => ViewMeal::route('/{record}'),
            'edit' => EditMeal::route('/{record}/edit'),
        ];
    }
}
