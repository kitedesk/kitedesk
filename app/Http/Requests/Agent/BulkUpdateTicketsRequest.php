<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTicketsRequest extends FormRequest
{
    use TicketPropertyRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            // Unknown ids are skipped by the controller, which loads all tickets in one query.
            'ids.*' => ['integer', 'distinct'],
            ...collect($this->ticketPropertyRules())->except(['tags', 'tags.*', 'custom_fields', 'category_id'])->all(),
        ];
    }
}
