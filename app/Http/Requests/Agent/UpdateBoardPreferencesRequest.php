<?php

namespace App\Http\Requests\Agent;

use App\Domain\Tickets\Support\BoardPreferences;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardPreferencesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tickets_layout' => ['sometimes', Rule::in(BoardPreferences::LAYOUTS)],
            'group_by' => ['sometimes', Rule::in(BoardPreferences::GROUP_BY)],
            'columns' => ['sometimes', 'array:'.implode(',', BoardPreferences::GROUP_BY)],
            'columns.*' => ['array:order,hidden'],
            'columns.*.order' => ['sometimes', 'array', 'max:200'],
            'columns.*.order.*' => ['string', 'max:40'],
            'columns.*.hidden' => ['sometimes', 'array', 'max:200'],
            'columns.*.hidden.*' => ['string', 'max:40'],
            'card_fields' => ['sometimes', 'array'],
            'card_fields.*' => ['distinct', Rule::in(BoardPreferences::CARD_FIELDS)],
            'reset' => ['sometimes', 'boolean'],
        ];
    }
}
