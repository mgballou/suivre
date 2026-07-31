<?php

declare(strict_types=1);

namespace App\Services\Meals\Data;

/**
 * One line of a meal as the user left it after confirming the classifier's
 * suggestion.
 *
 * `foodItemId` is the user's decision, not the classifier's: it is set when
 * they accepted (or corrected) the suggested catalog match, and null when they
 * rejected it or nothing was suggested. A null therefore means "this text has
 * no catalog entry", which is exactly what the review queue exists to answer.
 */
readonly class MealEntryDraft
{
    public function __construct(
        public string $text,
        public ?int $foodItemId,
    ) {}

    public function isResolved(): bool
    {
        return $this->foodItemId !== null;
    }
}
