<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Conditions\UpdateConditionActivationRequest;
use App\Models\Condition;
use Illuminate\Http\RedirectResponse;

class ConditionActivationController extends Controller
{
    /**
     * Stop or resume tracking a condition.
     *
     * Its own endpoint rather than a field on the edit form: stopping is one
     * tap from the list, and separating it keeps the edit form free to require
     * every field it shows.
     */
    public function __invoke(UpdateConditionActivationRequest $request, Condition $condition): RedirectResponse
    {
        $condition->update(['is_active' => $request->isActive()]);

        return to_route('conditions.index');
    }
}
