<?php

namespace App\Http\Requests\Agent;

use App\Domain\Ai\Agents\DraftImprover;
use App\Domain\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ask the assistant to rewrite the draft the agent is writing.
 */
class ImproveAiDraftRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'draft' => ['required', 'string', 'max:65000', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && RichText::isBlank($value)) {
                    $fail(__('Write something first.'));
                }
            }],
            'action' => ['required', Rule::in(DraftImprover::ACTIONS)],
            'instruction' => ['required_if:action,custom', 'nullable', 'string', 'max:500'],
        ];
    }
}
