<?php

namespace App\Http\Requests\Admin;

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGroupRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('groups', 'name')->ignore($group instanceof Group ? $group->id : null)],
            'description' => ['nullable', 'string', 'max:1000'],
            'assignment_mode' => ['sometimes', Rule::enum(AssignmentMode::class)],
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['integer', Rule::exists('users', 'id')->where('type', UserType::Staff->value)],
        ];
    }
}
