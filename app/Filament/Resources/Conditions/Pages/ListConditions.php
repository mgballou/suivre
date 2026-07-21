<?php

declare(strict_types=1);

namespace App\Filament\Resources\Conditions\Pages;

use App\Filament\Resources\Conditions\ConditionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConditions extends ListRecords
{
    protected static string $resource = ConditionResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
