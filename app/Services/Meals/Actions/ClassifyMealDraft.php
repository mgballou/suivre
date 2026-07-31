<?php

declare(strict_types=1);

namespace App\Services\Meals\Actions;

use App\Services\Food\Actions\ClassifyFoodEntry;
use App\Services\Meals\Data\ClassifiedLine;

class ClassifyMealDraft
{
    /**
     * Run every typed line of a draft meal through the classifier so the user
     * can confirm the suggestions before anything is saved (D9).
     *
     * Nothing is persisted here — this answers "what do you think these are?",
     * and `LogMeal` acts on whatever the user decides afterwards.
     *
     * @param  array<int, string>  $lines
     * @return array<int, ClassifiedLine>
     */
    public function __invoke(array $lines): array
    {
        $classify = app(ClassifyFoodEntry::class);

        return array_values(array_map(
            static fn (string $line): ClassifiedLine => ClassifiedLine::fromResult($line, $classify($line)),
            $lines,
        ));
    }
}
