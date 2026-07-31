<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewItems\Pages;

use App\Filament\Resources\ReviewItems\Actions\CatalogReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\DismissReviewItemAction;
use App\Filament\Resources\ReviewItems\Actions\ResolveReviewItemAction;
use App\Filament\Resources\ReviewItems\ReviewItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewReviewItem extends ViewRecord
{
    protected static string $resource = ReviewItemResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ResolveReviewItemAction::make(),
            CatalogReviewItemAction::make(),
            DismissReviewItemAction::make(),
        ];
    }
}
