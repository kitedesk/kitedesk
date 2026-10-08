<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Support\RichText;
use App\Http\Requests\Concerns\ChoosesStatusAfter;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            /** HTML body (sanitized). */
            'body' => ['required', 'string', 'max:65535'],
            /** Internal notes are only visible to staff. */
            'is_internal' => ['sometimes', 'boolean'],
            ...$this->statusAfterRules(),
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
                if (RichText::isBlank(RichText::sanitize((string) $this->input('body'))) && ! $this->hasFile('attachments')) {
                    $validator->errors()->add('body', __('Write a message or attach a file.'));
                }
            },
        ];
    }
}
