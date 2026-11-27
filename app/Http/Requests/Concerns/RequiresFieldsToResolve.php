<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Support\TicketCatalog;
use Closure;
use Illuminate\Validation\Validator;

/**
 * Solving or closing a ticket needs every required custom field that applies to it filled in,
 * counting the values and type sent with the same request.
 *
 * Only people are held to this; workflows and email replies update tickets directly.
 */
trait RequiresFieldsToResolve
{
    /**
     * An `after()` check that reports each missing required field.
     */
    protected function requiredFieldsCheck(): Closure
    {
        return function (Validator $validator): void {
            $ticket = $this->route('ticket');

            if (! $ticket instanceof Ticket || $validator->errors()->isNotEmpty() || ! $this->resolvesTicket()) {
                return;
            }

            $type = $this->has('type') ? $this->input('type') : $ticket->type;
            $values = [...($ticket->custom_fields ?? []), ...(array) $this->input('custom_fields', [])];

            foreach (TicketCatalog::fieldsFor($ticket->form, is_string($type) || $type === null ? $type : $type->value) as $field) {
                if ($field->isRequired() && ! TicketField::isFilled($values[$field->key] ?? null)) {
                    $validator->errors()->add("custom_fields.{$field->key}", __('Fill in ":field" before solving the ticket.', ['field' => $field->label]));
                }
            }
        };
    }

    /**
     * Whether this request moves the ticket to solved or closed.
     */
    private function resolvesTicket(): bool
    {
        if ($this->filled('ticket_status_id')) {
            $status = CustomStatus::query()->find($this->integer('ticket_status_id'))?->category;
        } else {
            $status = TicketStatus::tryFrom((string) $this->input('status'));
        }

        return $status !== null && ! $status->isUnresolved();
    }
}
