<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveWorkflowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The editor's "Test" button: runs the unsaved graph against a ticket without changing anything.
 */
class WorkflowTestController extends Controller
{
    public function __invoke(Request $request, GraphValidator $validator, WorkflowEngine $engine): JsonResponse
    {
        $request->validate([
            'graph' => ['required', 'array'],
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = $validator->validate($request->input('graph'));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $ticket = Ticket::query()->findOrFail($request->integer('ticket_id'));
        $graph = SaveWorkflowRequest::cleanGraph((array) $request->input('graph'));

        return response()->json($engine->simulate($graph, $ticket, $request->string('name')->toString() ?: __('Workflow')));
    }
}
