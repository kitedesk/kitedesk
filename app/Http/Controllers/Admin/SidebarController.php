<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Sidebar\SidebarSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSidebarRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The installation's default sidebar layout for agents. They can still pick their own.
 */
class SidebarController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/sidebar/edit', ['mode' => SidebarSettings::default()]);
    }

    public function update(SaveSidebarRequest $request): RedirectResponse
    {
        SidebarSettings::saveDefault($request->string('mode')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sidebar settings saved.')]);

        return back();
    }
}
