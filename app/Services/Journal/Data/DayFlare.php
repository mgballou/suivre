<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use App\Models\FlareEvent;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One acute flare already logged against a day.
 *
 * `time` and `duration` arrive pre-formatted: the client never derives either,
 * because `new Date()` reads the device timezone rather than the user's.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class DayFlare implements Arrayable
{
    public function __construct(
        public int $id,
        public string $conditionName,
        public string $hue,
        public string $intensity,
        public string $time,
        public ?string $duration,
        public ?string $note,
    ) {}

    /**
     * @return array{id: int, conditionName: string, hue: string, intensity: string, time: string, duration: string|null, note: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'conditionName' => $this->conditionName,
            'hue' => $this->hue,
            'intensity' => $this->intensity,
            'time' => $this->time,
            'duration' => $this->duration,
            'note' => $this->note,
        ];
    }

    /**
     * Requires the `condition` relation to be loaded; strict mode throws rather
     * than lazy-loading it once per flare.
     */
    public static function fromFlareEvent(FlareEvent $flareEvent, string $timezone): self
    {
        $duration = $flareEvent->duration_minutes;

        return new self(
            id: $flareEvent->id,
            conditionName: $flareEvent->condition->name,
            hue: $flareEvent->condition->color->value,
            intensity: $flareEvent->intensity->getLabel(),
            time: $flareEvent->occurred_at->setTimezone($timezone)->format('H:i'),
            duration: $duration === null ? null : self::humanise($duration),
            note: $flareEvent->note,
        );
    }

    private static function humanise(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $remainder === 0 ? "{$hours} h" : "{$hours} h {$remainder} min";
    }
}
