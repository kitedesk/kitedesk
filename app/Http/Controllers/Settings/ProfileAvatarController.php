<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Accounts\Support\UserAvatars;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileAvatarController extends Controller
{
    /**
     * Upload a new profile photo.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        UserAvatars::replace($request->user(), $request->file('avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Remove the profile photo.
     */
    public function destroy(Request $request): RedirectResponse
    {
        UserAvatars::remove($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo removed.')]);

        return to_route('profile.edit');
    }
}
