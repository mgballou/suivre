<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Condition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Only the conditions a user is currently tracking.
 *
 * Deactivating is how a condition leaves the day view; the row and every rating
 * and flare hanging off it stay, so this is a read-side filter and never a
 * delete. Every surface that lists "what I track today" taps this rather than
 * restating `where('is_active', true)`.
 */
class ActiveScope
{
    /**
     * @param  Builder<Condition>  $query
     */
    public function __invoke(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
