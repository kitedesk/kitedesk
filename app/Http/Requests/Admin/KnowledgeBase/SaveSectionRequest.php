<?php

namespace App\Http\Requests\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Models\Section;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $section = $this->route('section');
        $categoryId = $section instanceof Section ? $section->category_id : $this->integer('category_id');

        return [
            'category_id' => [$section instanceof Section ? 'prohibited' : 'required', 'integer', Rule::exists('kb_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('kb_sections', 'slug')
                    ->where('category_id', $categoryId)
                    ->ignore($section instanceof Section ? $section->id : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
