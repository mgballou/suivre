<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConditionLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->helperText("The user's local calendar day, not a UTC instant."),
                TextInput::make('intensity')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(10)
                    ->helperText('0–10, where 0 is symptom-free.'),
            ]);
    }
}
