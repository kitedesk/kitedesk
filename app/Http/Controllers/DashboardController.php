<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send staff to the agent workspace and customers to their portal.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isStaff()
            ? to_route('agent.tickets.index')
            : to_route('portal.tickets.index');
    }
}
