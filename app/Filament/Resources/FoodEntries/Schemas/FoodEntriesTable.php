<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Schemas;

use App\Enums\MealType;
use App\Models\FoodEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FoodEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('meal.user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('text')
                    ->label('Raw text')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),
                IconColumn::make('food_item_id')
                    ->label('Classified')
                    ->boolean()
                    ->tooltip(fn (FoodEntry $record): string => $record->food_item_id === null
                        ? 'Pending classification'
                        : "Catalog item #{$record->food_item_id}"),
                TextColumn::make('meal.meal_type')
                    ->label('Meal')
                    ->badge()
                    ->sortable(),
                TextColumn::make('eaten_at')
                    ->label('Eaten at')
                    ->dateTime()
                    ->placeholder('—')
                    ->tooltip('Inherited from the parent meal.'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('food_item_id')
                    ->label('Classification')
                    ->placeholder('All entries')
                    ->trueLabel('Classified')
                    ->falseLabel('Pending classification')
                    ->nullable(),
                SelectFilter::make('meal_type')
                    ->label('Meal type')
                    ->options(MealType::class)
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? [],
                        fn (Builder $query, array $values): Builder => $query->whereHas(
                            'meal',
                            fn (Builder $query): Builder => $query->whereIn('meal_type', $values),
                        ),
                    )),
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
