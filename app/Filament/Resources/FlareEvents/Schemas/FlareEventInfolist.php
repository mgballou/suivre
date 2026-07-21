<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FlareEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('condition.name')
                    ->label('Condition'),
                TextEntry::make('occurred_at')
                    ->dateTime(),
                TextEntry::make('intensity')
                    ->badge(),
                TextEntry::make('duration_minutes')
                    ->label('Duration')
                    ->numeric()
                    ->suffix(' min')
                    ->placeholder('—'),
                TextEntry::make('note')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
