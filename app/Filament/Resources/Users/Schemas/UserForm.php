<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    use PasswordValidationRules;

    public static function configure(Schema $schema): Schema
    {
        $rules = new self();

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rules($rules->passwordRules())
                    ->confirmed()
                    ->helperText('Tell them in person — nothing is emailed.'),
                TextInput::make('password_confirmation')
                    ->label('Confirm password')
                    ->password()
                    ->revealable()
                    ->required(),
                Select::make('timezone')
                    ->options(array_combine(
                        timezone_identifiers_list(),
                        timezone_identifiers_list(),
                    ))
                    ->searchable()
                    ->required()
                    // The journal is keyed on the account's local day, so this
                    // is the operator's to get right — defaulting it to their
                    // own is a starting point, not an answer.
                    ->default(function (): string {
                        $operator = auth()->user();

                        return $operator instanceof User
                            ? $operator->timezone
                            : config()->string('app.timezone');
                    })
                    ->helperText('The day boundary every meal and rating is filed against.'),
                Select::make('role')
                    ->options(UserRole::class)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->default(UserRole::Member)
                    ->helperText('An administrator uses this backstage and cannot use the journal. A member is the reverse.'),
            ]);
    }
}
