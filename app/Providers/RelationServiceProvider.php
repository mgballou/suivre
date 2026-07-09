<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DailyCheckin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class RelationServiceProvider extends ServiceProvider
{
    /**
     * The morph map is the single source of truth for polymorphic type strings.
     * Every polymorphic model registers its table-name string here.
     *
     * @var array<string, class-string<Model>>
     */
    private array $morphMap = [
        'daily_checkins' => DailyCheckin::class,
    ];

    public function boot(): void
    {
        Relation::enforceMorphMap($this->morphMap);
    }
}
