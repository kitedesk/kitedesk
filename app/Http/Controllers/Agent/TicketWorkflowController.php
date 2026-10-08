<?php

namespace App\Http\Controllers\Agent;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\Workflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Runs a manual workflow on a ticket ("Run workflow" in the ticket's menu).
 */
class TicketWorkflowController extends Controller
{
    public function store(Request $request, Ticket $ticket, Workflow $workflow, WorkflowEngine $engine): RedirectResponse
    {
        Gate::authorize('runWorkflows', $ticket);
        abort_unless($workflow->isRunnableBy($request->user()), 403);

        $engine->start($workflow, $ticket, startedBy: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow ":name" started.', ['name' => $workflow->name])]);

        return back();
    }
}
