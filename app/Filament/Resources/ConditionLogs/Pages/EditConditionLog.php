<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs\Pages;

use App\Filament\Resources\ConditionLogs\ConditionLogResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConditionLog extends EditRecord
{
    protected static string $resource = ConditionLogResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
