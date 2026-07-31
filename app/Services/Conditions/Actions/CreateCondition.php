<?php

declare(strict_types=1);

namespace App\Services\Conditions\Actions;

use App\Enums\ConditionHue;
use App\Models\Condition;
use App\Models\User;

class CreateCondition
{
    /**
     * The user app has no icon picker: colour is the identity channel (D20), so
     * every condition created from it takes the backstage's default glyph. The
     * column is not nullable, so the default lives here rather than in a schema
     * change that only the Filament table would notice.
     */
    private const string DEFAULT_ICON = 'heroicon-o-fire';

    /**
     * Start tracking a condition for a user.
     *
     * Invoked from both the conditions screen and first-run onboarding, which
     * creates several at once — the two must not drift on defaults.
     */
    public function __invoke(User $user, string $name, ConditionHue $color): Condition
    {
        return $user->conditions()->create([
            'name' => $name,
            'color' => $color,
            'icon' => self::DEFAULT_ICON,
            'is_active' => true,
        ]);
    }
}
