<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals\Schemas;

use App\Enums\MealType;
use App\Models\Meal;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('eaten_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('eaten_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Local day')
                    ->date(timezone: fn (Meal $record): string => $record->user->timezone)
                    ->placeholder('—')
                    ->tooltip("Derived from the owner's timezone (D5), not stored."),
                TextColumn::make('meal_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->label('Entries')
                    ->numeric()
                    ->sortable(),
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
                SelectFilter::make('meal_type')
                    ->options(MealType::class)
                    ->multiple(),
                Filter::make('empty')
                    ->label('No food entries')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('entries'))
                    ->toggle(),
                Filter::make('eaten_at')
                    ->schema([
                        DatePicker::make('from')->label('Eaten from'),
                        DatePicker::make('until')->label('Eaten until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('eaten_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('eaten_at', '<=', $until))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
