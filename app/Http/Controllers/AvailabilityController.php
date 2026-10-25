<?php

namespace App\Http\Controllers;

use App\Enums\Availability;
use App\Http\Requests\UpdateAvailabilityRequest;
use Illuminate\Http\RedirectResponse;

/**
 * A member says whether they take new conversations: available, busy or
 * away. Set from the menu under their name in the sidebar.
 */
class AvailabilityController extends Controller
{
    public function update(UpdateAvailabilityRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'availability' => $request->enum('availability', Availability::class),
        ])->save();

        return back();
    }
}
