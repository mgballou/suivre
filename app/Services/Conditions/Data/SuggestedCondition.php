<?php

declare(strict_types=1);

namespace App\Services\Conditions\Data;

use App\Enums\ConditionHue;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One condition offered during first-run onboarding, already carrying a hue so
 * a user who taps four of them ends up with four distinguishable colours
 * without being asked to choose any.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class SuggestedCondition implements Arrayable
{
    public function __construct(
        public string $name,
        public ConditionHue $hue,
    ) {}

    /**
     * @return array{name: string, hue: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hue' => $this->hue->value,
        ];
    }
}
