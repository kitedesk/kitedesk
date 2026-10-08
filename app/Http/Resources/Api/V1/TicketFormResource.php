<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use Illuminate\Http\Request;

/**
 * @mixin TicketForm
 */
class TicketFormResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('fields');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            /** The form's fields in order, and whether each is required. */
            'fields' => $this->fields->map(fn (TicketField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'required' => (bool) $field->pivot?->is_required,
            ])->values()->all(),
            'updated_at' => self::time($this->updated_at),
        ];
    }
}
