<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Pages;

use App\Filament\Resources\DailyCheckins\DailyCheckinResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyCheckin extends ViewRecord
{
    protected static string $resource = DailyCheckinResource::class;
}
