<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: the inbox figures once there are conversations, and the setup
 * steps with the ones already done ticked.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'setup' => [
                'team' => User::query()->count() > 1,
                'routing' => Tag::query()->exists(),
                'contacts' => Contact::query()->exists(),
            ],
        ]);
    }
}
