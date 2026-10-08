<?php

namespace App\Http\Requests\Portal;

use App\Domain\Tickets\Models\TicketCategory;
use App\Http\Requests\Agent\TicketPropertyRules;
use App\Http\Requests\Concerns\HasAttachments;
use App\Rules\Turnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestTicketRequest extends FormRequest
{
    use HasAttachments, TicketPropertyRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            ...$this->categoryRules(required: TicketCategory::query()->active()->visibleToCustomers()->exists(), customerFacing: true),
            ...$this->customFieldRules(customerFacing: true),
            ...Turnstile::rules(),
        ];
    }
}
