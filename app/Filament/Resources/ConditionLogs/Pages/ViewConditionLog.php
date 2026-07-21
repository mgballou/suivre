<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs\Pages;

use App\Filament\Resources\ConditionLogs\ConditionLogResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConditionLog extends ViewRecord
{
    protected static string $resource = ConditionLogResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
