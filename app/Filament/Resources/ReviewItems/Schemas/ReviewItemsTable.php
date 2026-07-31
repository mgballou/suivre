<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Schemas;

use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewItems\Actions\CatalogReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\DismissReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\ResolveReviewItemAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /*
             * Oldest first, and open-only by default. This is a work queue, not
             * a log: the operator wants the question that has been waiting
             * longest, and a decided item is only ever looked up on purpose.
             */
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('text')
                    ->label('Unmatched text')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('score')
                    ->label('Best score')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('no match')
                    ->tooltip('Trigram similarity of the closest catalog food. Below the confidence bar, or absent when nothing came close.')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Queued')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ReviewStatus::class)
                    ->multiple()
                    ->default(array_map(
                        static fn (ReviewStatus $status): string => $status->value,
                        ReviewStatus::open(),
                    )),
            ])
            ->recordActions([
                ResolveReviewItemAction::make(),
                CatalogReviewItemAction::make(),
                DismissReviewItemAction::make(),
                ViewAction::make(),
            ])
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('Every food the classifier could not place has been decided.');
    }
}
