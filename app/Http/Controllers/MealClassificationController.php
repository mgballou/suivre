<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Meals\ClassifyMealDraftRequest;
use App\Services\Meals\Actions\ClassifyMealDraft;
use App\Services\Meals\Data\ClassifiedLine;
use Illuminate\Http\JsonResponse;

class MealClassificationController extends Controller
{
    /**
     * Answer what the classifier makes of some typed lines, so the user can
     * confirm before the meal is saved (D9).
     *
     * JSON rather than an Inertia response: this is a lookup inside a form the
     * user has not finished, so re-rendering the page would throw away what
     * they have already typed.
     */
    public function __invoke(ClassifyMealDraftRequest $request): JsonResponse
    {
        $lines = app(ClassifyMealDraft::class)($request->lines());

        return new JsonResponse([
            'lines' => array_map(
                static fn (ClassifiedLine $line): array => $line->toArray(),
                $lines,
            ),
        ]);
    }
}
