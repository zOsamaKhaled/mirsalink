<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Support\TeamReport;
use App\Support\WorkingHours;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: today's figures (the team's for a supervisor, their own for an
 * agent), and the setup steps with the ones already done ticked.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $timezone = WorkingHours::current()->timezone;
        $today = now($timezone)->toDateString();
        $report = TeamReport::forDays($today, $today, $timezone);
        $open = fn () => Conversation::query()->where('status', ConversationStatus::Open);

        if ($user->isSupervisor()) {
            $overview = $report->overview();
            $stats = [
                'open' => $overview['open_now'],
                'waiting' => $overview['unassigned_now'],
                'reply' => $overview['median_reply'],
                'closed' => $overview['closed'],
            ];
        } else {
            $times = collect($report->replies())->where('user_id', $user->id)->pluck('seconds');
            $stats = [
                'open' => $open()->where('assigned_to', $user->id)->count(),
                'waiting' => $open()->whereNull('assigned_to')->count(),
                'reply' => $times->isEmpty() ? null : (int) round($times->median()),
                'closed' => collect($report->closures())->where('user_id', $user->id)->count(),
            ];
        }

        return Inertia::render('dashboard', [
            'stats' => [...$stats, 'mine' => ! $user->isSupervisor(), 'started' => Conversation::query()->exists()],
            'setup' => [
                'team' => User::query()->count() > 1,
                'routing' => Tag::query()->exists(),
                'contacts' => Contact::query()->exists(),
            ],
        ]);
    }
}
