<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveOrganizationRequest extends FormRequest
{
    /**
     * Accept domains as a comma/space separated string from the form.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domains'))) {
            $this->merge([
                'domains' => collect(preg_split('/[\s,]+/', $this->input('domains')) ?: [])
                    ->map(fn (string $domain): string => Str::lower(trim($domain)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domains' => ['nullable', 'array', 'max:50'],
            'domains.*' => ['string', 'max:255', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['domains.*.regex' => __('":input" is not a valid domain.')];
    }
}
