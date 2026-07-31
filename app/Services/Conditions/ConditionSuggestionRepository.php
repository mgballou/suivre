<?php

declare(strict_types=1);

namespace App\Services\Conditions;

use App\Enums\ConditionHue;
use App\Services\Conditions\Data\SuggestedCondition;

/**
 * The starting points offered on the first-run screen.
 *
 * Deliberately generic and few: the list exists so a new user has something to
 * tap rather than a blank field, not to be a clinical taxonomy. Anything not
 * here is typed in, and nothing here is required.
 */
class ConditionSuggestionRepository
{
    /**
     * @return array<int, SuggestedCondition>
     */
    public function all(): array
    {
        return [
            new SuggestedCondition('Joint pain', ConditionHue::Clay),
            new SuggestedCondition('Fatigue', ConditionHue::Ochre),
            new SuggestedCondition('Bloating', ConditionHue::Moss),
            new SuggestedCondition('Headache', ConditionHue::Marine),
            new SuggestedCondition('Brain fog', ConditionHue::Indigo),
            new SuggestedCondition('Skin flare', ConditionHue::Violet),
            new SuggestedCondition('Nausea', ConditionHue::Plum),
        ];
    }
}
