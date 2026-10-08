<?php

namespace App\Http\Requests\Agent;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required_without:email', 'nullable', 'integer', Rule::exists('users', 'id')],
            'email' => ['required_without:user_id', 'nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
