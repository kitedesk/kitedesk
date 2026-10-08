<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Accounts\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Toggle "away": agents who are away don't receive tickets from auto-assignment.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::ReceiveAssignments), 403);

        $user->forceFill(['is_available' => ! $user->is_available])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_available ? __("You're back. New tickets can be assigned to you.") : __("You're away. Auto-assignment will skip you."),
        ]);

        return back();
    }
}
