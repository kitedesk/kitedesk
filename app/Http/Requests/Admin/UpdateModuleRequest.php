<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['install', 'enable', 'disable', 'uninstall', 'update'])],
            // Uninstalling deletes the module's data, so it has to be asked for explicitly.
            'confirm' => ['exclude_unless:action,uninstall', 'accepted'],
        ];
    }
}
