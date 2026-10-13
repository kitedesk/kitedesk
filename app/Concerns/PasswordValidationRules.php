<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\NotRecentlyUsed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords. Given the person, their recent
     * passwords are refused as the password policy asks.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(?User $user = null): array
    {
        return ['required', 'string', Password::default(), 'confirmed', ...($user !== null ? [new NotRecentlyUsed($user)] : [])];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
