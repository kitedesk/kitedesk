<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Agent\TicketPropertyRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    use TicketPropertyRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            ...$this->ticketPropertyRules(),
        ];
    }
}
