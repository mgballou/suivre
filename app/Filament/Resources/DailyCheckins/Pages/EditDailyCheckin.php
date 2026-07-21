<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Pages;

use App\Filament\Resources\DailyCheckins\DailyCheckinResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyCheckin extends EditRecord
{
    protected static string $resource = DailyCheckinResource::class;

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
