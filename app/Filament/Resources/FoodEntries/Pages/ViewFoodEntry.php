<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Pages;

use App\Filament\Resources\FoodEntries\FoodEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFoodEntry extends ViewRecord
{
    protected static string $resource = FoodEntryResource::class;
}
