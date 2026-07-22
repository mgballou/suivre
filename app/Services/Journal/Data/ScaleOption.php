<?php

declare(strict_types=1);

namespace App\Services\Journal\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One tap target on a check-in scale.
 *
 * The options are projected from the domain enums rather than restated in the
 * client, so adding a case to MoodLevel/SleepQuality/StressLevel changes the UI
 * without a front-end edit.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class ScaleOption implements Arrayable
{
    public function __construct(
        public int $value,
        public string $label,
    ) {}

    /**
     * @return array{value: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
        ];
    }
}
