<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Pages;

use App\Filament\Resources\ReviewItems\ReviewItemResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewItems extends ListRecords
{
    protected static string $resource = ReviewItemResource::class;
}
