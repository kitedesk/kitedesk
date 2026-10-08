<?php

namespace App\Http\Requests\Setup;

use App\Domain\Support\Installation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! hash_equals(Installation::setupCode(), trim($value))) {
                    $fail(__('This setup code is not valid. Run `php artisan kitedesk:setup` to see it.'));
                }
            }],
            'helpdesk_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }
}
