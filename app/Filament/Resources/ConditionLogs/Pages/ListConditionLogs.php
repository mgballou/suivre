<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs\Pages;

use App\Filament\Resources\ConditionLogs\ConditionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListConditionLogs extends ListRecords
{
    protected static string $resource = ConditionLogResource::class;
}
