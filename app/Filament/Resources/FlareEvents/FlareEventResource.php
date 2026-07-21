<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlareEvents;

use App\Filament\Resources\FlareEvents\Pages\ListFlareEvents;
use App\Filament\Resources\FlareEvents\Pages\ViewFlareEvent;
use App\Filament\Resources\FlareEvents\Schemas\FlareEventInfolist;
use App\Filament\Resources\FlareEvents\Schemas\FlareEventsTable;
use App\Models\FlareEvent;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Backstage oversight of acute flare-ups — the event half of the hybrid
 * conditions model, and the outcome variable the correlation engine reads. It
 * is user-generated, logged in the user app; the backstage has read-only
 * visibility only, never create/edit/delete.
 *
 * @extends resource<FlareEvent>
 */
class FlareEventResource extends Resource
{
    protected static ?string $model = FlareEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Conditions';

    /**
     * @return Builder<FlareEvent>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'condition']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FlareEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlareEventsTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFlareEvents::route('/'),
            'view' => ViewFlareEvent::route('/{record}'),
        ];
    }
}
