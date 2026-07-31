<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Conditions\StoreConditionRequest;
use App\Http\Requests\Conditions\UpdateConditionRequest;
use App\Models\Condition;
use App\Models\User;
use App\Services\Conditions\Actions\CreateCondition;
use App\Services\Conditions\Data\ConditionSummary;
use App\Services\Conditions\Data\HueOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConditionController extends Controller
{
    /**
     * Everything the user tracks, retired ones included — an archived condition
     * has to stay visible to be resumed, and its accumulated ratings are the
     * evidence that stopping cost nothing.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $conditions = $user->conditions()
            ->withCount(['conditionLogs', 'flareEvents'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(ConditionSummary::fromCondition(...))
            ->map(static fn (ConditionSummary $summary): array => $summary->toArray())
            ->all();

        return Inertia::render('settings/conditions', [
            'conditions' => $conditions,
            'hues' => array_map(
                static fn (HueOption $option): array => $option->toArray(),
                HueOption::all(),
            ),
        ]);
    }

    public function store(StoreConditionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        app(CreateCondition::class)(
            user: $user,
            name: $request->name(),
            color: $request->color(),
        );

        return to_route('conditions.index');
    }

    public function update(UpdateConditionRequest $request, Condition $condition): RedirectResponse
    {
        $condition->update([
            'name' => $request->name(),
            'color' => $request->color(),
        ]);

        return to_route('conditions.index');
    }
}
