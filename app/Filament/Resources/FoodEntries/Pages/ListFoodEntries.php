<?php

declare(strict_types=1);

namespace App\Filament\Resources\FoodEntries\Pages;

use App\Filament\Resources\FoodEntries\FoodEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListFoodEntries extends ListRecords
{
    protected static string $resource = FoodEntryResource::class;
}
