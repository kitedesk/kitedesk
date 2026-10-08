<?php

namespace App\Http\Requests\Agent;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Requests\Concerns\ChoosesStatusAfter;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketMessageRequest extends FormRequest
{
    use ChoosesStatusAfter, HasAttachments;

    public function authorize(): bool
    {
        return $this->user()->can($this->boolean('is_internal') ? 'addInternalNote' : 'reply', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'is_internal' => ['sometimes', 'boolean'],
            'ai_assisted' => ['sometimes', 'boolean'],
            ...$this->statusAfterRules(),
            // Picked from the "Submit as" menu: becomes the agent's default for later replies.
            'remember_status' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            // Secrets go to the customer, so only with public replies.
            'secrets' => ['sometimes', 'array', 'max:10', Rule::prohibitedIf(fn (): bool => $this->boolean('is_internal'))],
            'secrets.*' => ['required', 'string', 'distinct', Rule::exists('secrets', 'token')
                ->where('ticket_id', $this->ticket()->id)
                ->where('created_by', $this->user()->id)
                ->whereNull('ticket_message_id')],
        ];
    }

    public function ticket(): Ticket
    {
        /** @var Ticket */
        return $this->route('ticket');
    }

    /**
     * Tokens of the secrets sent with the reply.
     *
     * @return list<string>
     */
    public function secretTokens(): array
    {
        return array_values(array_filter((array) $this->input('secrets', []), is_string(...)));
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (RichText::isBlank(RichText::sanitize((string) $this->input('body'))) && ! $this->hasFile('attachments') && $this->secretTokens() === []) {
                    $validator->errors()->add('body', __('Write a message or attach a file.'));
                }
            },
        ];
    }
}
