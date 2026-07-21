<?php

declare(strict_types=1);

namespace App\Filament\Resources\Meals\Pages;

use App\Filament\Resources\Meals\MealResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMeal extends ViewRecord
{
    protected static string $resource = MealResource::class;
}
