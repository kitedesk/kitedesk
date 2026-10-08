<?php

namespace App\Http\Requests\Admin\KnowledgeBase;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:up,down'],
        ];
    }

    public function movesUp(): bool
    {
        return $this->validated('direction') === 'up';
    }
}
