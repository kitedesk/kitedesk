<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Support\Sidebar\SidebarSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An agent's own choice of sidebar layout; `default` goes back to the installation's.
 */
class SidebarPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $mode = $request->validate(['mode' => ['required', Rule::in([...SidebarSettings::MODES, 'default'])]])['mode'];

        SidebarSettings::savePreference($request->user(), $mode === 'default' ? null : $mode);

        return back();
    }
}
