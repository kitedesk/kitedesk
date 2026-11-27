<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\TicketField;
use Illuminate\Http\Request;

/**
 * A custom field. Its `key` is the property name in a ticket's `custom_fields`.
 *
 * @mixin TicketField
 */
class TicketFieldResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            /** @var list<string>|null */
            'options' => $this->options,
            /** Shown on every ticket, whatever its form. */
            'is_global' => $this->is_global,
            /** Required when shown as a global field; form fields are required per form. */
            'is_required' => $this->is_required,
            /**
             * Ticket types the field applies to; null for all types.
             *
             * @var list<string>|null
             */
            'ticket_types' => $this->ticket_types,
            'is_visible_to_customers' => $this->is_visible_to_customers,
            'position' => $this->position,
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
