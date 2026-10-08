<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\EnumOptions;
use App\Domain\Support\Positions;
use App\Domain\Tickets\Actions\Setup\SaveTicketField;
use App\Domain\Tickets\Enums\TicketFieldType;
use App\Domain\Tickets\Models\TicketField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTicketFieldRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketFieldController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ticket-fields/index', [
            'fields' => TicketField::query()->ordered()->withCount('forms')->get(),
            'types' => EnumOptions::for(TicketFieldType::class),
        ]);
    }

    public function store(SaveTicketFieldRequest $request, SaveTicketField $saveField): RedirectResponse
    {
        $saveField->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field created.')]);

        return back();
    }

    public function update(SaveTicketFieldRequest $request, TicketField $ticketField, SaveTicketField $saveField): RedirectResponse
    {
        $saveField->update($ticketField, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field saved.')]);

        return back();
    }

    public function destroy(TicketField $ticketField): RedirectResponse
    {
        $ticketField->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field deleted. Existing ticket values are kept.')]);

        return back();
    }

    /**
     * Move a field one step up or down.
     */
    public function move(Request $request, TicketField $ticketField): RedirectResponse
    {
        /** @var 'up'|'down' $direction */
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Positions::move(TicketField::query(), $ticketField, $direction);

        return back();
    }
}
