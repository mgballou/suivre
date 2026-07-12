<?php

declare(strict_types=1);

namespace App\Events\Journal;

use App\Models\DailyCheckin;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailyCheckinRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DailyCheckin $checkin,
    ) {}
}
