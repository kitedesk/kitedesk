<?php

namespace App\Http\Requests\Admin;

use App\Domain\Accounts\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSecurityPolicyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_length' => ['required', 'integer', 'between:8,128'],
            'mixed_case' => ['required', 'boolean'],
            'numbers' => ['required', 'boolean'],
            'symbols' => ['required', 'boolean'],
            'uncompromised' => ['required', 'boolean'],
            'expires_after_days' => ['nullable', 'integer', 'between:30,365'],
            'remember_last' => ['required', 'integer', 'between:0,'.PasswordPolicy::MAX_REMEMBERED],
            'require_two_factor_for_staff' => ['required', 'boolean'],
        ];
    }

    public function policy(): PasswordPolicy
    {
        return new PasswordPolicy(
            $this->integer('min_length'),
            $this->boolean('mixed_case'),
            $this->boolean('numbers'),
            $this->boolean('symbols'),
            $this->boolean('uncompromised'),
            $this->filled('expires_after_days') ? $this->integer('expires_after_days') : null,
            $this->integer('remember_last'),
            $this->boolean('require_two_factor_for_staff'),
        );
    }
}
