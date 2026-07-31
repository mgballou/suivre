<?php

declare(strict_types=1);

namespace App\Services\Conditions\Data;

use App\Enums\ConditionHue;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One swatch in the hue picker, projected from ConditionHue so the curated set
 * is never restated client-side.
 *
 * `group` is the warm/cool split the picker lays the swatches out in — read
 * from the enum's set helpers rather than recomputed here.
 *
 * @implements Arrayable<string, mixed>
 */
readonly class HueOption implements Arrayable
{
    public function __construct(
        public string $value,
        public string $label,
        public string $group,
    ) {}

    /**
     * @return array{value: string, label: string, group: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'group' => $this->group,
        ];
    }

    public static function fromHue(ConditionHue $hue): self
    {
        return new self(
            value: $hue->value,
            label: $hue->getLabel(),
            group: $hue->isWarm() ? 'warm' : 'cool',
        );
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return array_map(self::fromHue(...), ConditionHue::ordered());
    }
}
