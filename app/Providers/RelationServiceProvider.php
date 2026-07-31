<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionLog;
use App\Models\DailyCheckin;
use App\Models\FlareEvent;
use App\Models\FoodEntry;
use App\Models\FoodItem;
use App\Models\FoodItemAlias;
use App\Models\Meal;
use App\Models\ReviewItem;
use App\Models\User;
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
        'categories' => Category::class,
        'meals' => Meal::class,
        'food_entries' => FoodEntry::class,
        'food_items' => FoodItem::class,
        'food_item_aliases' => FoodItemAlias::class,
        'review_items' => ReviewItem::class,
        // User participates in a morph relation via spatie's model_has_roles pivot.
        'users' => User::class,
    ];

    public function boot(): void
    {
        Relation::enforceMorphMap($this->morphMap);
    }
}
