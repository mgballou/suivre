<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Pages;

use App\Filament\Resources\DailyCheckins\DailyCheckinResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyCheckin extends ViewRecord
{
    protected static string $resource = DailyCheckinResource::class;

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
