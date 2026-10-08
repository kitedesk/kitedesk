<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Support\SatisfactionSurvey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSatisfactionSurveyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'delay_hours' => ['required', 'integer', Rule::in(SatisfactionSurvey::DELAYS)],
        ];
    }
}
