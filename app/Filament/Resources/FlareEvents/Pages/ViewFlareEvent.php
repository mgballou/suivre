<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Pages;

use App\Filament\Resources\FlareEvents\FlareEventResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFlareEvent extends ViewRecord
{
    protected static string $resource = FlareEventResource::class;

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
