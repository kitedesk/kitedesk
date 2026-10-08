<?php

namespace App\Http\Requests\Portal;

use App\Rules\Turnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['ticket' => ltrim((string) $this->input('ticket'), '# ')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'ticket' => ['required', 'string', 'max:40'],
            ...Turnstile::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ticket' => __('request number')];
    }
}
