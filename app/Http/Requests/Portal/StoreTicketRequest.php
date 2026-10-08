<?php

namespace App\Http\Requests\Portal;

use App\Domain\Tickets\Models\TicketCategory;
use App\Http\Requests\Agent\TicketPropertyRules;
use App\Http\Requests\Concerns\HasAttachments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    use HasAttachments, TicketPropertyRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            ...$this->categoryRules(required: $this->hasCustomerCategories(), customerFacing: true),
            ...$this->customFieldRules(customerFacing: true),
        ];
    }

    /**
     * Customers must pick a category whenever there are categories to pick from.
     */
    private function hasCustomerCategories(): bool
    {
        return TicketCategory::query()->active()->visibleToCustomers()->exists();
    }
}
