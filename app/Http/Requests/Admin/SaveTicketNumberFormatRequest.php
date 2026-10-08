<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Enums\TicketNumberReset;
use App\Domain\Tickets\Support\TicketNumberFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTicketNumberFormatRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'max:60', function (string $attribute, mixed $value, Closure $fail): void {
                $problem = TicketNumberFormat::problemWith((string) $value);

                if ($problem !== null) {
                    $fail($problem);
                }
            }],
            'reset' => ['required', Rule::enum(TicketNumberReset::class)],
            'next' => ['required', 'integer', 'min:1', 'max:999999999'],
        ];
    }
}
