<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->after(function (ValidationValidator $validator) use ($user): void {
            if ($user->isDeactivated()) {
                $validator->errors()->add('email', __('This account has been deactivated.'));
            }
        })->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
