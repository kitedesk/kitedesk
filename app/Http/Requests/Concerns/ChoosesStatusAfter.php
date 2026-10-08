<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Support\CustomStatuses;
use Illuminate\Validation\Rule;

/**
 * The status an agent picks when replying ("submit as ..."): a category in `status`, or an
 * admin-defined status in `ticket_status_id`. Neither may be New or Closed.
 */
trait ChoosesStatusAfter
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function statusAfterRules(): array
    {
        $cannotUpdate = fn (): bool => $this->user()->cannot('update', $this->route('ticket'));
        $excluded = [TicketStatus::New->value, TicketStatus::Closed->value];

        return [
            /** Category to apply after the message is added. */
            'status' => ['sometimes', 'nullable', Rule::prohibitedIf($cannotUpdate), Rule::enum(TicketStatus::class)->except([TicketStatus::New, TicketStatus::Closed])],
            /** Custom status to apply after the message is added; wins over `status`. */
            'ticket_status_id' => ['sometimes', 'nullable', 'integer', Rule::prohibitedIf($cannotUpdate), Rule::exists('ticket_statuses', 'id')->where('is_active', true)->whereNotIn('category', $excluded)],
        ];
    }

    public function statusAfter(): TicketStatus|CustomStatus|null
    {
        if ($this->filled('ticket_status_id')) {
            return CustomStatuses::find($this->integer('ticket_status_id'));
        }

        return $this->filled('status') ? TicketStatus::from($this->string('status')->toString()) : null;
    }
}
