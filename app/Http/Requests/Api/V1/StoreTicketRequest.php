<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Agent\TicketPropertyRules;
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
            /** ID of an existing user. Required unless `requester.email` is given. */
            'requester_id' => ['required_without:requester.email', 'nullable', 'integer', Rule::exists('users', 'id')],
            /** Requester email; a customer account is created when no user has this email. */
            'requester.email' => ['required_without:requester_id', 'nullable', 'email', 'max:255'],
            'requester.name' => ['required_with:requester.email', 'nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            /** HTML body of the first message (sanitized). */
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            ...collect($this->ticketPropertyRules(creating: true))->except(['status', 'ticket_status_id'])->all(),
        ];
    }
}
