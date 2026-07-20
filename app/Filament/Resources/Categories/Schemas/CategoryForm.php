<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryGroup;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (filled($get('slug'))) {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(Category::class, ignoreRecord: true)
                    ->helperText('Stable machine key — changing it re-keys historical classifications.'),
                Select::make('group')
                    ->options(CategoryGroup::class)
                    ->required()
                    ->native(false),
                Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('research_source')
                    ->maxLength(255)
                    ->helperText('Citation or URL backing a research-based category.')
                    ->columnSpanFull(),
            ]);
    }
}
