<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, ignoreRecord: true),
                Select::make('timezone')
                    ->options(array_combine(
                        timezone_identifiers_list(),
                        timezone_identifiers_list(),
                    ))
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->helperText('Authoritative for every derived local day (D5) — changing it re-buckets history.'),
            ]);
    }
}
