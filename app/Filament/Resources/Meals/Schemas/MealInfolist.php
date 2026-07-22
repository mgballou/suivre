<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals\Schemas;

use App\Models\Meal;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * The repeatable deliberately renders only stored entry columns. `FoodEntry`'s
 * `eaten_at` accessor reads back through `meal`, so surfacing it here would
 * need a `setRelation('meal', $record)` backfill on every entry; the meal's own
 * `eaten_at` above already carries that information.
 */
class MealInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('eaten_at')
                    ->dateTime(),
                TextEntry::make('date')
                    ->label('Local day')
                    ->date(timezone: fn (Meal $record): string => $record->user->timezone)
                    ->placeholder('—'),
                TextEntry::make('meal_type')
                    ->badge(),
                RepeatableEntry::make('entries')
                    ->label('Food entries')
                    ->placeholder('No entries logged.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('text')
                            ->label('Raw text')
                            ->placeholder('—'),
                        TextEntry::make('food_item_id')
                            ->label('Catalog item')
                            ->placeholder('Unclassified'),
                        IconEntry::make('food_item_id')
                            ->label('Classified')
                            ->boolean(),
                    ]),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
