<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Guests see the welcome page. Everyone else lands on their own side of the
     * app: a member on the calendar, an administrator in the backstage.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user->isAdmin()
                ? redirect()->to(Filament::getDefaultPanel()->getPath())
                : redirect()->route('calendar');
        }

        return Inertia::render('welcome');
    }
}
