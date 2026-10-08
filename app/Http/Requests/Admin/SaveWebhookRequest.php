<?php

namespace App\Http\Requests\Admin;

use App\Domain\Support\PublicNetwork;
use App\Domain\Webhooks\Enums\WebhookEvent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWebhookRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:https,http', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! PublicNetwork::allowsUrl($value)) {
                    $fail(__('The URL must point to a public internet address.'));
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::enum(WebhookEvent::class)],
            'is_active' => ['boolean'],
        ];
    }
}
