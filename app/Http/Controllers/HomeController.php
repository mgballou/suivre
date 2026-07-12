<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Guests see the welcome page; authenticated users land on the calendar.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('calendar');
        }

        return Inertia::render('welcome');
    }
}
