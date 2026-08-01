<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->copyable(),
                TextEntry::make('role')
                    ->badge(),
                TextEntry::make('timezone'),
                IconEntry::make('email_verified_at')
                    ->label('Email verified')
                    ->boolean(),
                TextEntry::make('two_factor_confirmed_at')
                    ->label('Two-factor confirmed')
                    ->dateTime()
                    ->placeholder('Not enabled'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
