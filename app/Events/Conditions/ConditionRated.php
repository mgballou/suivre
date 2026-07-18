<?php

declare(strict_types=1);

namespace App\Events\Conditions;

use App\Models\ConditionLog;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A condition's daily rating was recorded or updated for a local calendar day.
 */
class ConditionRated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ConditionLog $conditionLog,
    ) {}
}
