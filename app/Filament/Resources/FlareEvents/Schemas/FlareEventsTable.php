<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Schemas;

use App\Enums\FlareIntensity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FlareEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('condition.name')
                    ->label('Condition')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('intensity')
                    ->badge()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->numeric()
                    ->suffix(' min')
                    ->placeholder('—')
                    ->sortable(),
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
                SelectFilter::make('condition')
                    ->relationship('condition', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('intensity')
                    ->options(FlareIntensity::class)
                    ->multiple(),
                Filter::make('significant')
                    ->label('Clinically significant')
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'intensity',
                        array_column(FlareIntensity::significant(), 'value'),
                    ))
                    ->toggle(),
                Filter::make('occurred_at')
                    ->schema([
                        DatePicker::make('from')->label('Occurred from'),
                        DatePicker::make('until')->label('Occurred until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('occurred_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('occurred_at', '<=', $until))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
