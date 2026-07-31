<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Users\Actions\ResetUserPassword;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class ResetUserPasswordAction extends Action
{
    use PasswordValidationRules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Set password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->modalHeading('Set a new password')
            ->modalDescription('For an account whose owner is locked out. Tell them in person — nothing is emailed.')
            ->modalSubmitActionLabel('Set password')
            ->authorize(fn (User $record): bool => auth()->user()?->can('resetPassword', $record) ?? false)
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rules($this->passwordRules())
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('Confirm password')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(fn (User $record, array $data) => app(ResetUserPassword::class)(
                $record,
                (string) $data['password'],
            ))
            ->successNotificationTitle('Password set. Any "remember me" session they had is now void.');
    }

    public static function getDefaultName(): ?string
    {
        return 'resetPassword';
    }
}
