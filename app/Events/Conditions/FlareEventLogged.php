<?php

declare(strict_types=1);

namespace App\Events\Conditions;

use App\Models\FlareEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An acute flare-up of a condition was logged.
 */
class FlareEventLogged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FlareEvent $flareEvent,
    ) {}
}
