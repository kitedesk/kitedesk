<?php

namespace App\Http\Requests\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('kb_categories', 'slug')->ignore($category instanceof Category ? $category->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
