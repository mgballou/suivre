<?php

declare(strict_types=1);

namespace App\Filament\Resources\ConditionLogs\Pages;

use App\Filament\Resources\ConditionLogs\ConditionLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConditionLog extends ViewRecord
{
    protected static string $resource = ConditionLogResource::class;
}
