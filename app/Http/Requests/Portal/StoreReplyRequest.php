<?php

namespace App\Http\Requests\Portal;

use App\Domain\Support\RichText;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReplyRequest extends FormRequest
{
    use HasAttachments;

    public function authorize(): bool
    {
        return $this->user()->can('reply', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'mark_solved' => ['sometimes', 'boolean'],
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
