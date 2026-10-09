<?php

namespace App\Http\Requests\Agent;

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInternalRequestRequest extends FormRequest
{
    use HasAttachments;

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
            'group_id' => ['required', 'integer', Rule::exists(Group::class, 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'related_ticket_id' => ['nullable', 'integer', Rule::exists(Ticket::class, 'id')],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $related = $this->relatedTicket();

                if ($related !== null && ! $this->user()->can('view', $related)) {
                    $validator->errors()->add('related_ticket_id', __('You can only link tickets you can see.'));
                }
            },
        ];
    }

    public function relatedTicket(): ?Ticket
    {
        return $this->filled('related_ticket_id')
            ? Ticket::query()->find($this->integer('related_ticket_id'))
            : null;
    }
}
