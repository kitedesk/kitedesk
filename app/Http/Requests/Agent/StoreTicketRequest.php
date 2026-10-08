<?php

namespace App\Http\Requests\Agent;

use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use HasAttachments, TicketPropertyRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requester_id' => ['required_without:requester_email', 'nullable', 'integer', Rule::exists('users', 'id')],
            'requester_email' => ['required_without:requester_id', 'nullable', 'email', 'max:255'],
            'requester_name' => ['required_with:requester_email', 'nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            ...$this->ticketPropertyRules(creating: true),
        ];
    }
}
