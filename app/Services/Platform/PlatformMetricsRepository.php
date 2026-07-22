<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\ConditionLog;
use Carbon\CarbonImmutable;

/**
 * Read-side access to cross-user platform activity for the operator backstage.
 *
 * The admin panel oversees the app across *all* users — an operator has no
 * journal of their own — so these reads never scope to the acting user. They
 * answer "how much is the app being used", not "how is one person doing".
 */
class PlatformMetricsRepository
{
    /**
     * Count of condition entries logged across all users per calendar day,
     * keyed by `Y-m-d`. Days with no entries are absent, not zero.
     *
     * Aggregated in one grouped query so the window size, not the row count,
     * bounds the cost.
     *
     * @return array<string, int>
     */
    public function entriesLoggedPerDay(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return ConditionLog::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->selectRaw('date, count(*) as total')
            ->get()
            ->mapWithKeys(static function (ConditionLog $log): array {
                $total = $log->getAttribute('total');

                return [
                    $log->date->toDateString() => is_numeric($total) ? (int) $total : 0,
                ];
            })
            ->all();
    }
}
