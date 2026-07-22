<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FoodEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('meal.user.name')
                    ->label('User'),
                TextEntry::make('meal.meal_type')
                    ->label('Meal')
                    ->badge(),
                TextEntry::make('eaten_at')
                    ->label('Eaten at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('text')
                    ->label('Raw text')
                    ->placeholder('—')
                    ->columnSpanFull(),
                IconEntry::make('food_item_id')
                    ->label('Classified')
                    ->boolean(),
                TextEntry::make('food_item_id')
                    ->label('Catalog item')
                    ->numeric()
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
