<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A staff member whose role lets them be assigned tickets.
 */
class AssignableAgent implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! User::query()->assignable()->whereKey((int) $value)->exists()) {
            $fail(__('Choose an agent who can be assigned tickets.'));
        }
    }
}
