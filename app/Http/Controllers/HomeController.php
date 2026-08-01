<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Root is a signpost, not a screen. Everyone lands on their own side of the
     * app: a member on the calendar, an administrator in the backstage, and a
     * guest at the login form.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user->isAdmin()
                ? redirect()->to(Filament::getDefaultPanel()->getPath())
                : redirect()->route('calendar');
        }

        return redirect()->route('login');
    }
}
