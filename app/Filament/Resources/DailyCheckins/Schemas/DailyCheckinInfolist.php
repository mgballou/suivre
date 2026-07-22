<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DailyCheckinInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('sleep')
                    ->badge()
                    ->placeholder('—'),
                TextEntry::make('mood')
                    ->badge()
                    ->placeholder('—'),
                TextEntry::make('stress')
                    ->badge()
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
