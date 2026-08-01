<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Actions\ResetUserPasswordAction;
use App\Filament\Resources\Users\Actions\SetUserRoleAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('timezone')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean(),
                IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('meals_count')
                    ->label('Meals')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('daily_checkins_count')
                    ->label('Check-ins')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conditions_count')
                    ->label('Conditions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('flare_events_count')
                    ->label('Flares')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable(),
                TernaryFilter::make('two_factor_confirmed_at')
                    ->label('Two-factor enabled')
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                SetUserRoleAction::make(),
                ResetUserPasswordAction::make(),
            ]);
    }
}
