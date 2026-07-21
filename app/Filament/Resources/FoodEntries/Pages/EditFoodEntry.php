<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Pages;

use App\Filament\Resources\FoodEntries\FoodEntryResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFoodEntry extends EditRecord
{
    protected static string $resource = FoodEntryResource::class;

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
