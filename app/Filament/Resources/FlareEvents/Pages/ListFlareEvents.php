<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents\Pages;

use App\Filament\Resources\FlareEvents\FlareEventResource;
use Filament\Resources\Pages\ListRecords;

class ListFlareEvents extends ListRecords
{
    protected static string $resource = FlareEventResource::class;
}
