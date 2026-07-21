<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conditions\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('User')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->required(),
                TextInput::make('icon')
                    ->required()
                    ->maxLength(255)
                    ->helperText('A Heroicon name, e.g. heroicon-o-fire.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Archived conditions stay in history but stop being offered for logging.'),
            ]);
    }
}
