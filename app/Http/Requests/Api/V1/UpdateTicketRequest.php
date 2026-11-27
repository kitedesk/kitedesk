<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Agent\TicketPropertyRules;
use App\Http\Requests\Concerns\RequiresFieldsToResolve;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends FormRequest
{
    use RequiresFieldsToResolve, TicketPropertyRules;

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

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->requiredFieldsCheck()];
    }
}
