<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Accounts\Support\UserSessions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrowserSessionController extends Controller
{
    /**
     * Sign out of every other browser, after confirming the password.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = $request->user();

        // A new remember token stops "remember me" cookies elsewhere; this browser gets a fresh one.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logoutOtherDevices($request->string('password')->toString());
        UserSessions::forget($user, $request->session()->getId());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signed out of your other sessions.')]);

        return back();
    }
}
