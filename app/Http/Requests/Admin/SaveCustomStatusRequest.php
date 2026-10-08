<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomStatusRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $status = $this->status();

        return [
            // Default statuses may keep an empty name to show the translated category name.
            'name' => [$status?->is_default ? 'nullable' : 'required', 'string', 'max:60'],
            'category' => [
                $status === null ? 'required' : 'sometimes',
                Rule::enum(TicketStatus::class),
                Rule::prohibitedIf(fn (): bool => $status !== null && $this->input('category') !== $status->category->value && ($status->is_default || $status->tickets()->exists())),
            ],
            'color' => ['required', Rule::in(CustomStatus::COLORS)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean', Rule::prohibitedIf(fn (): bool => $status?->is_default === true && ! $this->boolean('is_active'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.prohibited' => __('The category of a default status, or of a status in use, cannot be changed.'),
            'is_active.prohibited' => __('The default status of a category cannot be turned off.'),
        ];
    }

    private function status(): ?CustomStatus
    {
        $status = $this->route('ticket_status');

        return $status instanceof CustomStatus ? $status : null;
    }
}
