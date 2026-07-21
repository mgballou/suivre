<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Schemas;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DailyCheckinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->helperText("The user's local calendar day, not a UTC instant."),
                Select::make('sleep')
                    ->options(SleepQuality::class)
                    ->native(false),
                Select::make('mood')
                    ->options(MoodLevel::class)
                    ->native(false),
                Select::make('stress')
                    ->options(StressLevel::class)
                    ->native(false),
                Textarea::make('note')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
