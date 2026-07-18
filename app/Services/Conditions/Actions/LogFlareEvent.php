<?php

declare(strict_types=1);

namespace App\Services\Conditions\Actions;

use App\Enums\FlareIntensity;
use App\Events\Conditions\FlareEventLogged;
use App\Exceptions\Conditions\ConditionNotOwnedException;
use App\Models\Condition;
use App\Models\FlareEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LogFlareEvent
{
    /**
     * Log an acute flare-up of a condition at a point in time.
     */
    public function __invoke(
        User $user,
        Condition $condition,
        CarbonImmutable $occurredAt,
        FlareIntensity $intensity,
        ?int $durationMinutes,
        ?string $note,
    ): FlareEvent {
        throw_if(
            condition: $condition->user_id !== $user->id,
            exception: ConditionNotOwnedException::make($user, $condition),
        );

        $flareEvent = DB::transaction(fn (): FlareEvent => FlareEvent::query()->create([
            'user_id' => $user->id,
            'condition_id' => $condition->id,
            'occurred_at' => $occurredAt,
            'intensity' => $intensity,
            'duration_minutes' => $durationMinutes,
            'note' => $note,
        ]));

        FlareEventLogged::dispatch($flareEvent);

        return $flareEvent;
    }
}
