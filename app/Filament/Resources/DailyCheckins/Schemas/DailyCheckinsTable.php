<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Schemas;

use App\Enums\MoodLevel;
use App\Enums\SleepQuality;
use App\Enums\StressLevel;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyCheckinsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('sleep')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('mood')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('stress')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('sleep')
                    ->options(SleepQuality::class)
                    ->multiple(),
                SelectFilter::make('mood')
                    ->options(MoodLevel::class)
                    ->multiple(),
                SelectFilter::make('stress')
                    ->options(StressLevel::class)
                    ->multiple(),
                Filter::make('incomplete')
                    ->label('Missing a signal')
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $query): Builder => $query
                            ->whereNull('sleep')
                            ->orWhereNull('mood')
                            ->orWhereNull('stress'),
                    ))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
