<?php

namespace App\Http\Requests\Agent;

use App\Domain\Secrets\Enums\SecretKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A secret request, or a secret to share with the customer.
 */
class StoreSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('useSecrets', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isShare = fn (): bool => $this->input('kind') === SecretKind::Share->value;

        return [
            'kind' => ['required', Rule::enum(SecretKind::class)],
            'label' => ['required', 'string', 'max:100'],
            'max_views' => ['required', 'integer', 'min:1', 'max:'.(int) config('kitedesk.secrets.max_views')],
            'expires_in_hours' => ['required', 'integer', 'min:1', 'max:'.(int) config('kitedesk.secrets.max_expiry_days') * 24],
            'secret' => [Rule::requiredIf($isShare), Rule::prohibitedIf(fn (): bool => ! $isShare()), 'string', 'max:'.(int) config('kitedesk.secrets.max_length')],
        ];
    }

    /**
     * The secret to share; null for requests.
     */
    public function content(): ?string
    {
        return $this->input('kind') === SecretKind::Share->value ? $this->string('secret')->toString() : null;
    }
}
