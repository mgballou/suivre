<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals\Schemas;

use App\Enums\MealType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('eaten_at')
                    ->required()
                    ->seconds(false)
                    ->helperText("An instant — the local day is derived from the owner's timezone (D5)."),
                Select::make('meal_type')
                    ->options(MealType::class)
                    ->required()
                    ->native(false),
            ]);
    }
}
