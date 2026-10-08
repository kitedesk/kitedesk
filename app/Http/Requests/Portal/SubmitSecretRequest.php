<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer's answer to a secret request.
 */
class SubmitSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('secret'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'secret' => ['required', 'string', 'max:'.(int) config('kitedesk.secrets.max_length')],
        ];
    }
}
