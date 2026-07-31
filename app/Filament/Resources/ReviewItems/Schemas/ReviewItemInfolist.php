<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReviewItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('text')
                    ->label('Unmatched text')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('score')
                    ->label('Best trigram score')
                    ->numeric(decimalPlaces: 3)
                    ->placeholder('nothing came close'),
                TextEntry::make('reviewable_type')
                    ->label('Queued from'),
                TextEntry::make('reviewable_id')
                    ->label('Record')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->label('Queued')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->label('Decided')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
