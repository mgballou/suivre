<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Conditions\StartTrackingConditionsRequest;
use App\Models\User;
use App\Services\Conditions\Actions\CreateCondition;
use App\Services\Conditions\ConditionSuggestionRepository;
use App\Services\Conditions\Data\SuggestedCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * First run: name what you track before the journal has anything to record
     * against. Deliberately outside RequireTrackedCondition — this is the
     * screen that gate sends people to — and it sends them straight on once
     * they have conditions, so re-visiting the URL is not a dead end.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->conditions()->exists()) {
            return to_route('calendar');
        }

        return Inertia::render('onboarding/conditions', [
            'suggestions' => array_map(
                static fn (SuggestedCondition $suggestion): array => $suggestion->toArray(),
                app(ConditionSuggestionRepository::class)->all(),
            ),
        ]);
    }

    public function store(StartTrackingConditionsRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        foreach ($request->conditions() as $condition) {
            app(CreateCondition::class)(
                user: $user,
                name: $condition->name,
                color: $condition->hue,
            );
        }

        return to_route('calendar');
    }
}
