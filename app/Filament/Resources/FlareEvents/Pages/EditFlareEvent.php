<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Pages;

use App\Filament\Resources\FlareEvents\FlareEventResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFlareEvent extends EditRecord
{
    protected static string $resource = FlareEventResource::class;

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
