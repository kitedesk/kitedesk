<?php

namespace App\Rules;

use App\Domain\Accounts\Support\PasswordPolicy;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Refuses the person's current password and the ones before it, as many as the password
 * policy remembers.
 */
class NotRecentlyUsed implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $remembered = PasswordPolicy::current()->rememberLast;

        if ($remembered === 0 || ! is_string($value)) {
            return;
        }

        $hashes = [
            $this->user->password,
            ...$this->user->passwordHistories()->latest('id')->limit($remembered - 1)->pluck('password'),
        ];

        foreach ($hashes as $hash) {
            if (Hash::check($value, $hash)) {
                $fail(__('Choose a password you haven’t used recently.'));

                return;
            }
        }
    }
}
