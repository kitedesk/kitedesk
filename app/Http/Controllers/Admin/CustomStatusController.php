<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\EnumOptions;
use App\Domain\Support\Positions;
use App\Domain\Tickets\Actions\Setup\DeleteCustomStatus;
use App\Domain\Tickets\Actions\Setup\SaveCustomStatus;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCustomStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomStatusController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ticket-statuses/index', [
            'statuses' => CustomStatus::query()->ordered()->withCount('tickets')->get()->map(fn (CustomStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'label' => $status->label(),
                'category' => $status->category->value,
                'color' => $status->color,
                'description' => $status->description,
                'is_default' => $status->is_default,
                'is_active' => $status->is_active,
                'tickets_count' => $status->tickets_count,
            ]),
            'categories' => EnumOptions::for(TicketStatus::class),
            'colors' => CustomStatus::COLORS,
        ]);
    }

    public function store(SaveCustomStatusRequest $request, SaveCustomStatus $saveStatus): RedirectResponse
    {
        $saveStatus->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status created.')]);

        return back();
    }

    public function update(SaveCustomStatusRequest $request, CustomStatus $ticketStatus, SaveCustomStatus $saveStatus): RedirectResponse
    {
        $saveStatus->update($ticketStatus, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status saved.')]);

        return back();
    }

    /**
     * Delete a status. Its tickets move to the default status of the same category.
     */
    public function destroy(CustomStatus $ticketStatus, DeleteCustomStatus $deleteStatus): RedirectResponse
    {
        try {
            $deleteStatus->handle($ticketStatus);
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status deleted. Its tickets moved to :status.', [
            'status' => CustomStatuses::defaultFor($ticketStatus->category)->label(),
        ])]);

        return back();
    }

    /**
     * Make this the status tickets get when they move into its category.
     */
    public function makeDefault(CustomStatus $ticketStatus): RedirectResponse
    {
        DB::transaction(function () use ($ticketStatus): void {
            CustomStatus::query()->where('category', $ticketStatus->category)->whereKeyNot($ticketStatus->id)->update(['is_default' => false]);
            $ticketStatus->forceFill(['is_default' => true, 'is_active' => true])->save();
        });

        return back();
    }

    /**
     * Move a status one step up or down within its category.
     */
    public function move(Request $request, CustomStatus $ticketStatus): RedirectResponse
    {
        /** @var 'up'|'down' $direction */
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Positions::move(CustomStatus::query()->where('category', $ticketStatus->category), $ticketStatus, $direction);

        return back();
    }
}
