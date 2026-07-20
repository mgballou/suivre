<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\DailyCheckin;
use App\Models\FlareEvent;
use App\Models\FoodEntry;
use App\Models\Meal;
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
        'conditions' => Condition::class,
        'condition_logs' => ConditionLog::class,
        'flare_events' => FlareEvent::class,
        'meals' => Meal::class,
        'food_entries' => FoodEntry::class,
    ];

    public function boot(): void
    {
        Relation::enforceMorphMap($this->morphMap);
    }
}
