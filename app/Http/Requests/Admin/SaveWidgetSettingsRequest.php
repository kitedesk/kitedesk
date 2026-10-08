<?php

namespace App\Http\Requests\Admin;

use App\Domain\Widget\WidgetSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveWidgetSettingsRequest extends FormRequest
{
    /**
     * Hosts are compared lowercase and without a trailing slash.
     */
    protected function prepareForValidation(): void
    {
        $domains = $this->input('allowed_domains');

        if (is_array($domains)) {
            $this->merge([
                'allowed_domains' => collect($domains)
                    ->map(fn (mixed $domain): mixed => is_string($domain) ? rtrim(Str::lower(trim($domain)), '/') : $domain)
                    ->reject(fn (mixed $domain): bool => $domain === null || $domain === '')
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
            'enabled' => ['required', 'boolean'],
            'allowed_domains' => ['present', 'array', 'max:50'],
            'allowed_domains.*' => ['string', 'max:255', 'regex:'.WidgetSettings::DOMAIN_PATTERN],
            'position' => ['required', Rule::in(WidgetSettings::POSITIONS)],
            'launcher_label' => ['nullable', 'string', 'max:30'],
            'greeting' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowed_domains.*.regex' => __('Enter a domain such as example.com or *.example.com.'),
        ];
    }
}
