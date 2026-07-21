<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals\Pages;

use App\Filament\Resources\Meals\MealResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMeal extends ViewRecord
{
    protected static string $resource = MealResource::class;

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
