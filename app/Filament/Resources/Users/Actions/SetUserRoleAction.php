<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\Actions\SetUserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class SetUserRoleAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Set role')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->modalHeading('Set the account role')
            ->modalDescription('An administrator reaches this backstage and can read every account\'s journal. A member cannot.')
            ->modalSubmitActionLabel('Set role')
            ->authorize(fn (User $record): bool => auth()->user()?->can('setRole', $record) ?? false)
            ->schema([
                Select::make('role')
                    ->label('Role')
                    ->options(UserRole::class)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->default(fn (User $record): ?string => $record->role?->value),
            ])
            // A Select built from the enum hands back the case itself, not its
            // value; the cast is the fallback for a plainly-typed option list.
            ->action(function (User $record, array $data): void {
                $role = $data['role'];

                app(SetUserRole::class)(
                    $record,
                    $role instanceof UserRole ? $role : UserRole::from((string) $role),
                );
            })
            ->successNotificationTitle('Role set.');
    }

    public static function getDefaultName(): ?string
    {
        return 'setRole';
    }
}
