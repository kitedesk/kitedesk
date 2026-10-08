<?php

namespace App\Http\Requests\Admin\KnowledgeBase;

use App\Domain\KnowledgeBase\Enums\ArticleStatus;
use App\Domain\KnowledgeBase\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveArticleRequest extends FormRequest
{
    /**
     * Derive the slug from the title when it is left empty.
     */
    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge(['slug' => Str::slug($slug !== '' ? $slug : (string) $this->input('title'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $article = $this->route('article');

        return [
            'section_id' => ['required', 'integer', Rule::exists('kb_sections', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('kb_articles', 'slug')->ignore($article instanceof Article ? $article->id : null),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:200000'],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
        ];
    }
}
