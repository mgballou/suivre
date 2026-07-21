<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FoodEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('text')
                    ->label('Raw text')
                    ->rows(2)
                    ->requiredWithout('food_item_id')
                    ->helperText('A check constraint requires either raw text or a catalog item.')
                    ->columnSpanFull(),
                TextInput::make('food_item_id')
                    ->label('Catalog item ID')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->requiredWithout('text')
                    ->helperText('A raw key until the `food_items` catalog lands with SUI-14.'),
            ]);
    }
}
