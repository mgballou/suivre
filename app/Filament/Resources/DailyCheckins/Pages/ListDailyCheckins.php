<?php

declare(strict_types=1);

namespace App\Filament\Resources\DailyCheckins\Pages;

use App\Filament\Resources\DailyCheckins\DailyCheckinResource;
use Filament\Resources\Pages\ListRecords;

class ListDailyCheckins extends ListRecords
{
    protected static string $resource = DailyCheckinResource::class;
}
