<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ask the assistant for a reply (or internal note), optionally based on help center articles.
 */
class GenerateAiDraftRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:public,internal'],
            'article_ids' => ['sometimes', 'array', 'max:5'],
            'article_ids.*' => ['integer', 'distinct'],
            'instruction' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<int>
     */
    public function articleIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('article_ids', [])));
    }
}
