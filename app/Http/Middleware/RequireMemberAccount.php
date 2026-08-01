<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The other half of the split. `User::canAccessPanel()` keeps a member out of
 * the backstage; this keeps an administrator out of the user app — the journal
 * and the account settings alike. An account belongs to exactly one side.
 *
 * An administrator is an operator identity: it can read every account's health
 * records, which is precisely why it should not also be somebody's journal.
 * Sending them to the backstage rather than refusing outright makes a stray link
 * land somewhere useful instead of on a wall.
 *
 * Deliberately *not* applied to the authentication routes. Logging out is a
 * `web` route like any other, and an administrator bounced away from it could
 * not sign out at all.
 */
class RequireMemberAccount
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->to(Filament::getDefaultPanel()->getPath());
        }

        return $next($request);
    }
}
