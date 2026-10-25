<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\UpdateWorkingHoursRequest;
use App\Support\TeamTimezones;
use App\Support\WorkingHours;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team's working week and the reply a customer gets outside it.
 */
class WorkingHoursController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('team/hours', [
            'hours' => WorkingHours::current()->toArray(),
            'timezones' => TeamTimezones::ALL,
        ]);
    }

    public function update(UpdateWorkingHoursRequest $request): RedirectResponse
    {
        $current = WorkingHours::current();

        WorkingHours::fromArray([
            'timezone' => $request->string('timezone')->toString(),
            'days' => $request->input('days'),
            'auto_reply_enabled' => $request->boolean('auto_reply_enabled'),
            'auto_reply_message' => $request->filled('auto_reply_message')
                ? $request->string('auto_reply_message')->toString()
                : $current->autoReplyMessage,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.team.flash.hours_saved')]);

        return to_route('team.hours.edit');
    }
}
