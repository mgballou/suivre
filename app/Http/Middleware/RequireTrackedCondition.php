<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first-run gate: the journal is meaningless until the user has named what
 * they track, so the calendar, the day screen and insights redirect to
 * onboarding until at least one condition exists.
 *
 * The test is "has ever defined one", not "has an active one" — deactivating
 * every condition must not throw a long-standing user back into onboarding.
 * Onboarding, settings and auth deliberately sit outside this group, so there
 * is no route the gate can bounce a request between.
 */
class RequireTrackedCondition
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->conditions()->doesntExist()) {
            return redirect()->route('onboarding.conditions');
        }

        return $next($request);
    }
}
