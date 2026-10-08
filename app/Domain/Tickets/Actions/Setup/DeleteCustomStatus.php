<?php

namespace App\Domain\Tickets\Actions\Setup;

use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Tickets\Support\TicketViews;
use App\Domain\Workflows\Support\WorkflowReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a status. Its tickets move to the default status of the same category, which itself
 * can't be deleted.
 */
class DeleteCustomStatus
{
    /**
     * @throws ValidationException
     */
    public function handle(CustomStatus $status): void
    {
        if ($status->is_default) {
            throw ValidationException::withMessages(['status' => __('The default status of a category cannot be deleted.')]);
        }

        DB::transaction(function () use ($status): void {
            Ticket::query()
                ->where('ticket_status_id', $status->id)
                ->update(['ticket_status_id' => CustomStatuses::defaultFor($status->category)->id]);

            $status->delete();
        });

        WorkflowReferences::deleted('status', $status->id, $status->label());
        TicketViews::forgetCounts();
    }
}
