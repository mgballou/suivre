<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Schemas;

use App\Enums\FlareIntensity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FlareEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required()
                    ->seconds(false),
                Select::make('intensity')
                    ->options(FlareIntensity::class)
                    ->required()
                    ->native(false),
                TextInput::make('duration_minutes')
                    ->label('Duration (minutes)')
                    ->numeric()
                    ->integer()
                    ->minValue(1),
                Textarea::make('note')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
