<?php

namespace App\Http\Requests\Agent;

use App\Domain\Tickets\Models\Ticket;
use App\Rules\AssignableAgent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Forward a ticket to one agent (`assignee_id`) or to a group's queue (`group_id`).
 */
class ForwardTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('forward', $this->ticket());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => [
                'nullable',
                'integer',
                'required_without:group_id',
                'prohibits:group_id',
                new AssignableAgent,
                Rule::notIn(array_filter([$this->user()->id, $this->ticket()->assignee_id])),
            ],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignee_id.required_without' => __('Choose an agent or a group.'),
            'assignee_id.not_in' => __('Pick a different agent.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ticket = $this->ticket();

                if ($this->filled('group_id') && $this->integer('group_id') === $ticket->group_id && $ticket->assignee_id === null) {
                    $validator->errors()->add('group_id', __('This ticket is already waiting in that group\'s queue.'));
                }
            },
        ];
    }

    public function ticket(): Ticket
    {
        /** @var Ticket */
        return $this->route('ticket');
    }
}
